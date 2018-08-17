<?php # -*- coding: utf-8 -*-

/**
 * Displays an element link flyout tab in the frontend.
 * @author  fefe
 * @version 2018.07.03
 */
class Mlp_Language_Selector implements Mlp_Updatable {

	/**
	 * Prefix for 'name' attribute in form fields.
	 *
	 * @var string
	 */
	private $form_name = 'mlp-language-selector-extra';

	/**
	 * @var Mlp_Assets_Interface
	 */
	private $assets;

	/**
	 * @var Mlp_Language_Api_Interface
	 */
	private $language_api;

	/**
	 * @var Mlp_Module_Manager_Interface
	 */
	private $module_manager;

	/**
	 * @var Inpsyde_Nonce_Validator
	 */
	private $nonce_validator;

	/**
	 * @var Mlp_Translation[]
	 */
	private $translations = array();

	/**
	 * Constructor. Sets up the properties.
	 *
	 * @param Mlp_Module_Manager_Interface $module_manager Module manager object.
	 * @param Mlp_Language_Api_Interface   $language_api   Language API object.
	 * @param Mlp_Assets_Interface         $assets         Asset manager object.
	 */
	public function __construct(
		Mlp_Module_Manager_Interface $module_manager,
		Mlp_Language_Api_Interface $language_api,
		Mlp_Assets_Interface $assets
	) {

		$this->module_manager = $module_manager;

		$this->language_api = $language_api;

		$this->assets = $assets;

		$this->nonce_validator = Mlp_Nonce_Validator_Factory::create( 'save_language_selector_position' );
	}

	/**
	 * Wires up all functions.
	 *
	 * @return void
	 */
	public function initialize() {

		// Quit here if module is turned off
		if ( ! $this->register_setting() ) {
			return;
		}

		if ( is_admin() ) {
			add_action( 'mlp_modules_add_fields', array( $this, 'draw_options_page_form_fields' ) );

			// Use this hook to handle the user input of your modules' options page form fields
			add_filter( 'mlp_modules_save_fields', array( $this, 'save_options_page_form_fields' ) );
		} else {
			$url = (string) filter_input( INPUT_POST, 'mlp_language_selector_select' );
			if ( '' !== $url ) {
				$this->redirect_quick_link( $url );
			}
			
			add_action( 'mlp_language_selector', array( $this, 'frontend_selector' ));
			
			add_action( 'wp_head', array( $this, 'load_style' ), 0 );
		}
	}
	
	/**
	 * Requires the stylesheet.
	 *
	 * @return bool
	 */
	public function load_style() {
		/*$theme_support = get_theme_support( 'multilingualpress' );
		if ( ! empty( $theme_support[0]['quicklink_style'] ) ) {
			return false;
		}*/

		return $this->assets->provide( 'mlp_frontend_css' );
	}

	/**
	 * Nothing to do here.
	 *
	 * @param string $name
	 *
	 * @return void
	 */
	public function update( $name ) {
	}

	/**
	 * Registers the module.
	 *
	 * @return bool
	 */
	private function register_setting() {

		return $this->module_manager->register( array(
			'description'  => __( 'Show language selector and redirects to the current page in translated languages.', 'multilingual-press' ),
			'display_name' => __( 'Language Selector', 'multilingual-press' ),
			'slug'         => 'class-' . __CLASS__,
			'state'        => 'off',
		) );
	}

	/**
	 * Catches quicklink submissions and redirects if the URL is valid.
	 *
	 * @since 1.0.4
	 *
	 * @param string $url The URL that is to be redirected to.
	 *
	 * @return void
	 */
	private function redirect_quick_link( $url ) {

		$callback = array( $this, 'extend_allowed_hosts' );
		add_filter( 'allowed_redirect_hosts', $callback, 10, 2 );

		$url = wp_validate_redirect( $url, false );

		remove_filter( 'allowed_redirect_hosts', $callback );

		if ( ! $url ) {
			return;
		}

		// Force GET request.
		wp_redirect( $url, 303 );
		mlp_exit();
	}

	/**
	 * Adds all domains of the network to the allowed hosts.
	 *
	 * @wp-hook allowed_redirect_hosts
	 *
	 * @since 1.0.4
	 *
	 * @param string[] $home_hosts  Array with one entry: the host of home_url().
	 * @param string   $remote_host Host name of the URL to validate.
	 *
	 * @return string[]
	 */
	public function extend_allowed_hosts( array $home_hosts, $remote_host ) {

		// Network with sub directories.
		if ( in_array( $remote_host, $home_hosts, true ) ) {
			return $home_hosts;
		}

		/** @var wpdb $wpdb */
		global $wpdb;

		$query = sprintf(
			'
SELECT domain
FROM %s
WHERE site_id = %d
	AND public   = "1"
	AND archived = "0"
	AND mature   = "0"
	AND spam     = "0"
	AND deleted  = "0"
ORDER BY domain DESC',
			$wpdb->blogs,
			$wpdb->siteid
		);

		// @codingStandardsIgnoreLine
		$domains = $wpdb->get_col( $query );

		$allowed_hosts = array_merge( $home_hosts, $domains );
		$allowed_hosts = array_unique( $allowed_hosts );

		return $allowed_hosts;
	}

	/**
	 * Deletes the according site option on module deactivation.
	 *
	 * @since 0.1
	 *
	 * @return void
	 */
	public static function deactivate_module() {

		delete_site_option( 'inpsyde_multilingual_language_selector_options' );
	}

	public function frontend_selector() {	
		$current_blog_id = get_current_blog_id();
	
		$languages = $this->language_api->get_site_languages( $current_blog_id );

		//Get current settings
		$extra_options = get_site_option( 'inpsyde_multilingual_language_selector_options' );
		
		$translations = $this->get_translations();
						
		$languages_links = array();
		
		foreach ($languages as $site_id => $language_code ) {
			
			if ( ! empty( $extra_options['mlp_language_selector_extra_' . $site_id] ) ) {
				if ( ! $extra_options['mlp_language_selector_extra_' . $site_id] ) {
					continue;
				}
			} else {
				continue;
			}
		
			$language_code = mlp_get_blog_language( $site_id ,false );
			
			$language_link = array(
					'site_id'     => $site_id,
					'language_code' => $language_code,
					'language_short' => mlp_get_blog_language( $site_id ,true ),
					'language_full' => ucfirst( locale_get_display_language( $language_code, $language_code ) ),
					'url'      => '#',
			);
			
			if ( ! empty( $translations[$site_id] ) ) {
				$language_link['url'] = $translations[$site_id]->get_remote_url();
			} else { 
				if ( $site_id !== $current_blog_id ) {
					$language_link['url'] = get_home_url( $site_id );
				}
			}

			$languages_links[] = $language_link;
		}
		
		echo $this->to_html( $languages_links, $current_blog_id );
	}
	
	/**
	 * Returns the translations.
	 *
	 * @return Mlp_Translation_Interface[]
	 */
	private function get_translations() {

		$type = '';
	
		if ( ! ( is_singular() || is_category() || is_tag() || is_front_page() || is_home() || is_search() ) ) {
			return array();
		}

		if ( $this->translations ) {
			return $this->translations;
		}
		
		//first check front_page cause front page is_singular, but is_singular is not necessarly front_page
		if ( is_front_page() ) {
			$type = 'front_page';
		}
		elseif ( is_singular() || is_home() ) {
			$type = 'post';
		}
		elseif ( is_category() || is_tag() ) {
			$type = 'term';
		}
		elseif ( is_search() ) {
			$type = 'search';
		}
		
		$this->translations = $this->language_api->get_translations( array(
			'type' => $type,
			'include_base' => true,
		) );
		
		return $this->translations;
	}

	protected function to_html( array $languages_links, $current_blog_id ) {

		$type = 'links';
		$element = 'a';
		$glue = '</li><li>';

		$elements = array();

		$rel = 'alternate';

		foreach ( $languages_links as $language_link ) {
			$attributes = array(
				'href'     => $language_link['url'],
				'hreflang' => $language_link['language_code'],
				'rel'      => $rel,
			);

			if ( $language_link['site_id'] ===  $current_blog_id) {
				$attributes['class'] = 'mlp-current-language';
			}
			
			$attributes_html = '';

			foreach ( $attributes as $key => $value ) {
				$attributes_html .= ' ' . $key . '="' . esc_attr( $value ) . '"';
			}
			
			$elements[] = sprintf(
				'<%1$s%2$s>%3$s</%1$s>',
				$element,
				$attributes_html,
				strtoupper( $language_link['language_short'] )
			);		
		}	
		
		$html = '<ul><li>' . implode( $glue, $elements ) . '</li></ul>';

		return $this->get_html_container( $html, $translated );
	}
	
	/**
	 * Returns the remote post links in form of up to three link elements, or a select element for more than three
	 * links.
	 *
	 * @param  string $selections 'option' or 'a' elements.
	 *
	 * @return string
	 */
	protected function get_html_container( $selections ) {

			$html = <<<HTML
<div class="mlp-language-selector">
		$selections
</div>
HTML;
		return $html;
	}

	/**
	 * Displays the module options page form fields.
	 *
	 * @since 0.1
	 *
	 * @return void
	 */
	public function draw_options_page_form_fields() {

		$data = new Mlp_Language_Selector_Data( $this->nonce_validator, $this->get_settings() );

		$box = new Mlp_Extra_General_Settings_Box( $data );
		$box->print_box();
	}

	/**
	 * Saves module user input.
	 *
	 * @since 0.1
	 *
	 * @return void
	 */
	public function save_options_page_form_fields() {

		if ( ! $this->nonce_validator->is_valid() ) {
			return;
		}

		// Get current site options
		$options = get_site_option( 'inpsyde_multilingual_language_selector_options' );
		
		$settings = $this->get_settings();
		$options    = (array) get_site_option( 'inpsyde_multilingual_language_selector_options' );

		foreach ( $settings as $setting ) {
			if ( empty( $_POST[ $this->form_name ][ $setting['id'] ]  ) ) {
				$options['mlp_language_selector_extra_' . $setting['id']] = false;
			} else {
				$options['mlp_language_selector_extra_' . $setting['id']] = true;
			}
		}

		update_site_option( 'inpsyde_multilingual_language_selector_options', $options );
	}
	
	/**
	 * Returns the keys and labels for the positions.
	 *
	 * @return string[]
	 */
	private function get_settings() {

		$out = array();
	
		$languages = $this->language_api->get_site_languages( 0 );
		
		foreach ($languages as $site_id => $blog_title ) {
			$option = array(
				'id' 			=> $site_id,
				'display_name'  => $blog_title,
				'description'   => '',
			);

			$out[] = $option;
		}
			
		return $out;
	}
}
