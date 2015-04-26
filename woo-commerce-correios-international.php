<?php
/*
Plugin Name: Correios International
Plugin URI: https://github.com/RcDevLabs/WooCommerce-Correios-International/
Description: International shipping with Correios from Brazil for WooCommerce
Version: 1.0.0
Author: RCDevLabs
Author URI: http://rcdevlabs.github.io
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if WooCommerce is active
 */

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	
	if ( ! class_exists( 'WC_Correios_Package' ) ) {
		include_once './wc_package.php';
	};

	function international_correios_init() {
		if ( ! class_exists( 'WC_Correios_International' ) ) {
			class WC_Correios_International extends WC_Shipping_Method {
				/**
				 * Constructor for your shipping class
				 *
				 * @access public
				 * @return void
				 */
				public function __construct() {
					// Logger.
					if ( class_exists( 'WC_Logger' ) ) {
						$this->log = new WC_Logger();
					} else {
						$this->log = $this->woocommerce_method()->logger();
					}
					$this->id                 = 'international_correios'; // Id for International Correios. Should be uunique.
					$this->method_title       = __( 'International Correios' );  // Title shown in admin
					$this->method_description = __( 'International Shipment via Correios worldwide' ); // Description shown in admin

					$this->enabled            = $this->get_option( 'enabled' );
					$this->title              = "Worldwide"; // This can be added as an setting but for this example its forced.

					$this->init();
				}

				/**
				 * Init your settings
				 *
				 * @access public
				 * @return void
				 */
				function init() {
					// Load the settings API
					$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
					$this->init_settings(); // This is part of the settings API. Loads settings you previously init.

					// Save settings in admin if you have any defined
					add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
				}

				/**
				 * calculate_shipping function.
				 *
				 * @access public
				 * @param mixed $package
				 * @return void
				 */
				public function calculate_shipping( $package ) {
					$this->country= $package['destination']['country'];
					$this->package = new WC_Correios_Package( $package ); //santa classe

					$package = $this->package->get_data();
					$this->height = $package['height'];
					$this->width  = $package['width'];
					$this->length = $package['length'];
					$this->weight = $package['weight']*1000;
					
					//http://www2.correios.com.br/sistemas/efi/bb/Consulta.cfm?
					//tipoConsulta=Geral
					//&PAIS=US
					//&UFORIGEM=CE
					//&LOCALIDADE=C
					//&ALTURA=15
					//&LARGURA=15
					//&PROFUNDIDADE=15
					//&PESO=1000
					//&RESET=TRUE
					//&ESPECIF=110, sendo:
					// 110 Mercadoria Expressa (EMS)
					// 128 Mercadoria Econômica
					// 209 Leve Internacional

					$args = array(
						'tipoConsulta'	 => 'geral'
						, 'especif' 		 => '110'
						, 'largura'			 => $this->width
						, 'altura'			 => $this->height
						, 'profundidade' => $this->length
						, 'peso' 				 => $this->weight
						, 'uforigem' 		 => $this->get_option( 'uforigem' )
						, 'localidade' 	 => $this->get_option( 'localidade' )
						, 'pais'					 =>$this->country
						, 'reset'				 => true
						);
					$webserviceurl = 'http://www2.correios.com.br/sistemas/efi/bb/Consulta.cfm?';
					$url = add_query_arg( $args, $webserviceurl );
					$response = wp_remote_get( $url, array( 'sslverify' => false, 'timeout' => 30 ) );

					if ( is_wp_error( $response ) ) {
						//erro
						$rate = array(
							'id' => $this->id,
							'label' => 'ERR',
							'cost' => '20.99',
							'calc_tax' => 'per_item'
							);
							// Register the rate
							$this->add_rate( $rate );

					} elseif ( $response['response']['code'] >= 200 && 
										 $response['response']['code'] < 300 ) {
						//deu algo
						$result = new SimpleXmlElement( $response['body'], LIBXML_NOCDATA );
						$resposta = $result->tipo_servico;
						$erro = $result->erro;
						$cod_erro = $erro->codigo;
						$dados = $resposta->dados_postais;
						$prazo = (string) $dados->prazo_entrega;
						$valor = (string) $dados->preco_postal;
						$values[ $code ] = $service;
						$matches = array();
						preg_match_all('!\d+!', $prazo, $matches);
						if($coderro != ''){
						
							switch ($cod_erro) {
								case 2:
									# erro 2 = país não suporta serviço
									break;
								
								default:
									# code...
									break;
							}
						return $values;
						} else {
								$rate = array(
								'id' => $this->id,
								'label' => 'EMS: '.implode(' to ',array_reverse($matches[0])).' working days',
								'cost' => $valor,
								'calc_tax' => 'per_item'
								);
								// Register the rate
								$this->add_rate( $rate );
						}
					} else {
						//erro:  webservice inacessível
					}

				}

		public function init_form_fields() {
	     $this->form_fields = array(
	     	'enabled' => array(
				'title'            => __( 'Enable/Disable', 'woocommerce' ),
				'type'             => 'checkbox',
				'label'            => __( 'Ativar o correios international', 'woocommerce' ),
				'default'          => 'no'
				),
	     'uforigem' => array(
	          'title' => __( 'UF de origem', 'woocommerce' ),
	          'type' => 'text',
	          'description' => __( 'Sigla do estado de qual será postado a mercadoria (padrão: SC)', 'woocommerce' ),
	          'default' => __( 'SC', 'woocommerce' )
	          ),
	     'localidade' => array(
	          'title' => __( 'Capital ou Interior?', 'woocommerce' ),
	          'type' => 'text',
	          'description' => __( 'A cidade é capital ou interior? Preenhca com C ou I. (Padrão: C)', 'woocommerce' ),
	          'default' => __("C", 'woocommerce')
       			 )
	     );
			} // End init_form_fields()
			public function admin_options() {
			  echo '<h2>';
			  echo __('International Correios','woocommerce');
			  echo '</h2>';
			  echo '<table class="form-table">';
			  $this->generate_settings_html();
			  echo '</table>';
			}
		}// end class WC_Correios_International
	}
		
}

	add_action( 'woocommerce_shipping_init', 'international_correios_init' );

	function add_international_correios( $methods ) {
		$methods[] = 'WC_Correios_International';
		return $methods;
	}

	add_filter( 'woocommerce_shipping_methods', 'add_international_correios' );
}





