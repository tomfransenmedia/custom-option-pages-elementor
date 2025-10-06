<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'elementor/dynamic_tags/register_tags', function( $dynamic_tags ) {

    class Business_Info_Tag extends \Elementor\Core\DynamicTags\Tag {

        public function get_name() {
            return 'business-info-tag';
        }

        public function get_title() {
            return __( 'Custom Business Info', 'textdomain' );
        }

        public function get_group() {
            return 'site';
        }

        public function get_categories() {
            return [
                \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
                \Elementor\Modules\DynamicTags\Module::URL_CATEGORY,
                \Elementor\Modules\DynamicTags\Module::MEDIA_CATEGORY,
            ];
        }

        // NOTE: don't call get_settings() here — that can return null while Elementor builds controls.
        protected function register_controls() {
            $posts = $this->fetch_business_info_posts_for_select();
            $first_post_id = ! empty( $posts ) ? array_key_first( $posts ) : '';

            $this->add_control(
                'business_post_id',
                [
                    'label'   => __( 'Select Business Info Post', 'textdomain' ),
                    'type'    => \Elementor\Controls_Manager::SELECT,
                    'options' => $posts,
                    'default' => $first_post_id,
                ]
            );

            // Build ACF fields options WITHOUT relying on $this->get_settings()
            // we provide fields for every post aggregated — this avoids calling get_settings during control init
            $acf_field_options = $this->fetch_all_acf_fields_for_select();

            $this->add_control(
                'acf_field_name',
                [
                    'label'       => __( 'Select ACF Field', 'textdomain' ),
                    'type'        => \Elementor\Controls_Manager::SELECT,
                    'options'     => $acf_field_options,
                    'description' => __( '<i>Save & <a href="#" onclick="location.reload(); return false;">refresh</a> if fields don’t match the post.</i>', 'textdomain' ),
                ]
            );

            $this->add_control(
                'array_key',
                [
                    'label'       => __( 'Array Key', 'textdomain' ),
                    'type'        => \Elementor\Controls_Manager::TEXT,
                    'placeholder' => 'e.g. url, title, etc.',
                ]
            );
        }

        // When rendering or returning value, we *can* safely call get_settings()
        public function get_value( array $options = [] ) {
            $selected = $this->get_settings( 'acf_field_name' );
            if ( ! $selected ) return '';

            // expected format: "{post_id}||{field_key_or_name}"
            if ( strpos( $selected, '||' ) === false ) {
                return '';
            }

            list( $post_id, $field_identifier ) = explode( '||', $selected, 2 );
            $post_id = intval( $post_id );

            $value = '';

            if ( function_exists( 'get_field' ) ) {
                // ACF get_field accepts field key OR name
                $value = get_field( $field_identifier, $post_id );
            } else {
                $value = get_post_meta( $post_id, $field_identifier, true );
            }

            // subfield inside group (fallback): if value empty and identifier looks like "group_sub"
            if ( empty( $value ) && strpos( $field_identifier, '_' ) !== false ) {
                $parts = explode( '_', $field_identifier );
                if ( count( $parts ) >= 2 ) {
                    $subfield_name = array_pop( $parts );
                    $group_name    = implode( '_', $parts );
                    $group         = function_exists( 'get_field' ) ? get_field( $group_name, $post_id ) : get_post_meta( $post_id, $group_name, true );
                    if ( is_array( $group ) && isset( $group[ $subfield_name ] ) ) {
                        $value = $group[ $subfield_name ];
                    }
                }
            }

            // arrays (repeaters, file arrays, etc.)
            if ( is_array( $value ) ) {
                $key = $this->get_settings( 'array_key' );
                if ( $key && isset( $value[ $key ] ) ) {
                    $value = $value[ $key ];
                } else {
                    // if numerically indexed, take first element, else reset() for first assoc value
                    if ( isset( $value[0] ) ) {
                        $value = $value[0];
                    } else {
                        $value = reset( $value );
                    }
                }
            }

            // If MEDIA category requested, let the other logic handle in render or return appropriate structure where needed
            return wp_kses_post( (string) $value );
        }

        protected function render() {
            $value = $this->get_value();
            if ( ! is_array( $value ) ) {
                echo $value;
            }
        }

        // --- Helper: get posts for the select (safe during register_controls) ---
        private function fetch_business_info_posts_for_select() {
            $options = [];
            $posts = get_posts( [
                'post_type'      => 'business-info',
                'posts_per_page' => 50,
                'post_status'    => [ 'publish', 'private' ],
                'orderby'        => 'title',
                'order'          => 'ASC',
                'fields'         => 'ids',
            ] );

            if ( ! $posts ) {
                return $options;
            }

            foreach ( $posts as $post_id ) {
                $options[ $post_id ] = get_the_title( $post_id ) ? get_the_title( $post_id ) : ( 'ID ' . $post_id );
            }

            return $options;
        }

        // --- Helper: aggregate all ACF fields across all business-info posts (safe during register_controls) ---
        private function fetch_all_acf_fields_for_select() {
            $options = [];

            if ( ! function_exists( 'get_field_objects' ) ) {
                return $options;
            }

            $posts = get_posts( [
                'post_type'      => 'business-info',
                'posts_per_page' => 50,
                'post_status'    => [ 'publish', 'private' ],
            ] );

            foreach ( $posts as $post ) {
                $fields = get_field_objects( $post->ID );
                if ( ! $fields || ! is_array( $fields ) ) {
                    continue;
                }

                foreach ( $fields as $name => $field ) {
                    // Skip groups here (we only list top-level non-group fields)
                    if ( isset( $field['type'] ) && $field['type'] === 'group' ) {
                        continue;
                    }
                    // We store option as: postid||field_name_or_key
                    $key = $post->ID . '||' . ( isset( $field['name'] ) ? $field['name'] : $name );
                    $label = ( get_the_title( $post->ID ) ?: 'ID ' . $post->ID ) . ' → ' . ( isset( $field['label'] ) ? $field['label'] : $name );
                    $options[ $key ] = $label;
                }
            }

            return $options;
        }

    }

    $dynamic_tags->register( new Business_Info_Tag() );
} );