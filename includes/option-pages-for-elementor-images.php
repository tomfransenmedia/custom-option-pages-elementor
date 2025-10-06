<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'elementor/dynamic_tags/register_tags', function( $dynamic_tags ) {

    class Business_Info_Image_Tag extends \Elementor\Core\DynamicTags\Data_Tag {

        public function get_name() {
            return 'business-info-image';
        }

        public function get_title() {
            return __( 'Business Info Image', 'textdomain' );
        }

        public function get_group() {
            return 'site';
        }

        public function get_categories() {
            return [ \Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY ];
        }

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

            // Provide image fields aggregated across all business-info posts (safe during control init)
            $this->add_control(
                'acf_field_name',
                [
                    'label'   => __( 'Select ACF Image Field', 'textdomain' ),
                    'type'    => \Elementor\Controls_Manager::SELECT,
                    'options' => $this->fetch_all_image_fields_for_select(),
                ]
            );
        }

        public function get_value( array $options = [] ) {
            $selected = $this->get_settings( 'acf_field_name' );
            $post_id  = intval( $this->get_settings( 'business_post_id' ) );

            if ( ! $selected || ! $post_id ) {
                return [];
            }

            $value = function_exists( 'get_field' ) ? get_field( $selected, $post_id ) : get_post_meta( $post_id, $selected, true );

            // Case: ACF returns image array
            if ( is_array( $value ) && isset( $value['ID'] ) && ( isset( $value['url'] ) || isset( $value['sizes'] ) ) ) {
                // try to build url
                $url = isset( $value['url'] ) ? $value['url'] : wp_get_attachment_url( $value['ID'] );
                return [
                    'id'  => intval( $value['ID'] ),
                    'url' => esc_url( $url ),
                ];
            }

            // If numeric attachment ID
            if ( is_numeric( $value ) && wp_attachment_is_image( $value ) ) {
                return [
                    'id'  => intval( $value ),
                    'url' => wp_get_attachment_url( $value ),
                ];
            }

            // If direct URL
            if ( is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ) {
                $attachment_id = attachment_url_to_postid( $value );
                return [
                    'id'  => $attachment_id ?: 0,
                    'url' => esc_url( $value ),
                ];
            }

            return [];
        }

        private function fetch_business_info_posts_for_select() {
            $options = [];
            $posts = get_posts( [
                'post_type'      => 'business-info',
                'posts_per_page' => 50,
                'post_status'    => [ 'publish', 'private' ],
                'orderby'        => 'title',
                'order'          => 'ASC',
            ] );

            if ( ! $posts ) {
                return $options;
            }

            foreach ( $posts as $p ) {
                $options[ $p->ID ] = get_the_title( $p->ID ) ? get_the_title( $p->ID ) : 'ID ' . $p->ID;
            }

            return $options;
        }

        private function fetch_all_image_fields_for_select() {
            $options = [];

            if ( ! function_exists( 'get_field_objects' ) ) {
                return $options;
            }

            $posts = get_posts( [
                'post_type'      => 'business-info',
                'posts_per_page' => 50,
                'post_status'    => [ 'publish', 'private' ],
            ] );

            foreach ( $posts as $p ) {
                $fields = get_field_objects( $p->ID );
                if ( ! $fields || ! is_array( $fields ) ) continue;

                foreach ( $fields as $name => $field ) {
                    if ( isset( $field['type'] ) && $field['type'] === 'image' ) {
                        // key format: postid||field_name - readable label includes post title
                        $key = $p->ID . '||' . ( isset( $field['name'] ) ? $field['name'] : $name );
                        $label = ( get_the_title( $p->ID ) ?: 'ID ' . $p->ID ) . ' → ' . ( isset( $field['label'] ) ? $field['label'] : $name );
                        $options[ $key ] = $label;
                    }
                }
            }

            return $options;
        }
    }

    $dynamic_tags->register( new Business_Info_Image_Tag() );
} );