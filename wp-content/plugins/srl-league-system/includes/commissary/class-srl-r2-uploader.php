<?php
/**
 * Cloudflare R2 / S3-Compatible Direct Uploader
 * Location: wp-content/plugins/srl-league-system/includes/commissary/class-srl-r2-uploader.php
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

class SRL_R2_Uploader {

    /**
     * Check if Cloudflare R2 is configured and enabled.
     *
     * @return bool
     */
    public static function is_enabled() {
        $enabled     = get_option( 'srl_r2_enabled', 0 );
        $account_id  = get_option( 'srl_r2_account_id', '' );
        $access_key  = get_option( 'srl_r2_access_key_id', '' );
        $secret_key  = get_option( 'srl_r2_secret_access_key', '' );
        $bucket_name = get_option( 'srl_r2_bucket_name', '' );

        return ( ! empty( $enabled ) && ! empty( $account_id ) && ! empty( $access_key ) && ! empty( $secret_key ) && ! empty( $bucket_name ) );
    }

    /**
     * Send a signed AWS SigV4 request to R2.
     *
     * @param string $method HTTP method (GET, PUT, DELETE)
     * @param string $object_key Target object key
     * @param string $file_content Body content (for PUT)
     * @param string $mime_type MIME type (for PUT)
     * @return array|WP_Error Response from wp_remote_request
     */
    private static function send_signed_request( $method, $object_key, $file_content = '', $mime_type = '' ) {
        $account_id  = trim( get_option( 'srl_r2_account_id', '' ) );
        $access_key  = trim( get_option( 'srl_r2_access_key_id', '' ) );
        $secret_key  = trim( get_option( 'srl_r2_secret_access_key', '' ) );
        $bucket_name = trim( get_option( 'srl_r2_bucket_name', '' ) );

        $host     = $account_id . '.r2.cloudflarestorage.com';
        $endpoint = 'https://' . $host . '/' . $bucket_name . '/' . ltrim( $object_key, '/' );
        $region   = 'auto';
        $service  = 's3';

        // AWS SigV4 calculation
        $timestamp = time();
        $amz_date  = gmdate( 'Ymd\THis\Z', $timestamp );
        $date_stamp = gmdate( 'Ymd', $timestamp );

        $payload_hash = hash( 'sha256', $file_content );

        $canonical_uri = '/' . $bucket_name . '/' . ltrim( $object_key, '/' );
        $canonical_querystring = '';
        
        $canonical_headers = "host:" . $host . "\n" . "x-amz-content-sha256:" . $payload_hash . "\n" . "x-amz-date:" . $amz_date . "\n";
        $signed_headers = 'host;x-amz-content-sha256;x-amz-date';

        $canonical_request = $method . "\n" . $canonical_uri . "\n" . $canonical_querystring . "\n" . $canonical_headers . "\n" . $signed_headers . "\n" . $payload_hash;

        $algorithm = 'AWS4-HMAC-SHA256';
        $credential_scope = $date_stamp . '/' . $region . '/' . $service . '/aws4_request';
        $string_to_sign = $algorithm . "\n" . $amz_date . "\n" . $credential_scope . "\n" . hash( 'sha256', $canonical_request );

        $k_secret  = 'AWS4' . $secret_key;
        $k_date    = hash_hmac( 'sha256', $date_stamp, $k_secret, true );
        $k_region  = hash_hmac( 'sha256', $region, $k_date, true );
        $k_service = hash_hmac( 'sha256', $service, $k_region, true );
        $k_signing = hash_hmac( 'sha256', 'aws4_request', $k_service, true );
        $signature = hash_hmac( 'sha256', $string_to_sign, $k_signing );

        $authorization_header = $algorithm . ' ' . 'Credential=' . $access_key . '/' . $credential_scope . ', ' . 'SignedHeaders=' . $signed_headers . ', ' . 'Signature=' . $signature;

        $headers = [
            'Host'                 => $host,
            'x-amz-date'           => $amz_date,
            'x-amz-content-sha256' => $payload_hash,
            'Authorization'        => $authorization_header,
        ];
        
        if ( ! empty( $mime_type ) ) {
            $headers['Content-Type'] = $mime_type;
        }

        $args = [
            'method'    => $method,
            'headers'   => $headers,
            'timeout'   => 60,
        ];
        
        if ( $method === 'PUT' || $method === 'POST' ) {
            $args['body'] = $file_content;
        }

        return wp_remote_request( $endpoint, $args );
    }

    /**
     * Get an object from R2.
     *
     * @param string $object_key Target object key.
     * @return array Array with success status and body/error message.
     */
    public static function get_object( $object_key ) {
        if ( ! self::is_enabled() ) {
            return [ 'success' => false, 'error' => 'R2 not enabled' ];
        }

        $response = self::send_signed_request( 'GET', $object_key );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return [ 'success' => false, 'error' => 'HTTP ' . $code, 'code' => $code ];
        }

        return [ 'success' => true, 'body' => wp_remote_retrieve_body( $response ) ];
    }

    /**
     * Delete an object from R2.
     *
     * @param string $object_key Target object key.
     * @return array Array with success status and error message.
     */
    public static function delete_object( $object_key ) {
        if ( ! self::is_enabled() ) {
            return [ 'success' => false, 'error' => 'R2 not enabled' ];
        }

        $response = self::send_signed_request( 'DELETE', $object_key );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 204 && $code !== 200 ) {
            return [ 'success' => false, 'error' => 'HTTP ' . $code, 'code' => $code ];
        }

        return [ 'success' => true ];
    }

    /**
     * Upload a file to Cloudflare R2.
     *
     * @param string $file_path Local path to file.
     * @param string $file_name Target filename/object key.
     * @param string $mime_type Content MIME type.
     * @return array Array with success status, url, and error message if failed.
     */
    public static function upload_file( $file_path, $file_name, $mime_type = 'application/octet-stream' ) {
        if ( ! file_exists( $file_path ) ) {
            return [ 'success' => false, 'error' => 'El archivo temporal no existe.' ];
        }

        $file_content = file_get_contents( $file_path );
        if ( $file_content === false ) {
            return [ 'success' => false, 'error' => 'No se pudo leer el archivo para subir a R2.' ];
        }

        $clean_name = sanitize_file_name( $file_name );
        $object_key = 'evidence/' . date( 'Y/m/' ) . wp_generate_password( 8, false ) . '-' . $clean_name;

        $response = self::send_signed_request( 'PUT', $object_key, $file_content, $mime_type );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 && $code !== 201 ) {
            $body = wp_remote_retrieve_body( $response );
            return [ 'success' => false, 'error' => 'Error R2 HTTP ' . $code . ': ' . wp_strip_all_tags( $body ) ];
        }

        $public_url  = untrailingslashit( trim( get_option( 'srl_r2_public_url', '' ) ) );
        $account_id  = trim( get_option( 'srl_r2_account_id', '' ) );
        $bucket_name = trim( get_option( 'srl_r2_bucket_name', '' ) );
        $host     = $account_id . '.r2.cloudflarestorage.com';
        $endpoint = 'https://' . $host . '/' . $bucket_name . '/' . ltrim( $object_key, '/' );

        $final_url = ! empty( $public_url ) ? $public_url . '/' . ltrim( $object_key, '/' ) : $endpoint;

        return [
            'success' => true,
            'url'     => $final_url,
            'key'     => $object_key,
        ];
    }

    /**
     * Generate a presigned S3/R2 PUT URL for direct client-side uploads.
     * Allows browsers to upload directly to Cloudflare R2 bypassing all WordPress PHP size limits.
     *
     * @param string $file_name Target filename.
     * @param string $mime_type Content MIME type.
     * @param int    $expires_seconds Duration in seconds (default 1800 = 30 min).
     * @return array Array with success status, upload_url, public_url, and key.
     */
    public static function get_presigned_put_url( $file_name, $mime_type = 'application/octet-stream', $expires_seconds = 1800 ) {
        if ( ! self::is_enabled() ) {
            return [ 'success' => false, 'error' => 'Cloudflare R2 no está configurado o habilitado.' ];
        }

        $account_id  = trim( get_option( 'srl_r2_account_id', '' ) );
        $access_key  = trim( get_option( 'srl_r2_access_key_id', '' ) );
        $secret_key  = trim( get_option( 'srl_r2_secret_access_key', '' ) );
        $bucket_name = trim( get_option( 'srl_r2_bucket_name', '' ) );
        $public_url  = untrailingslashit( trim( get_option( 'srl_r2_public_url', '' ) ) );

        // Clean object key
        $clean_name = sanitize_file_name( $file_name );
        $object_key = 'evidence/' . gmdate( 'Y/m/' ) . wp_generate_password( 8, false ) . '-' . $clean_name;

        $host     = $account_id . '.r2.cloudflarestorage.com';
        $region   = 'auto';
        $service  = 's3';

        $timestamp  = time();
        $amz_date   = gmdate( 'Ymd\THis\Z', $timestamp );
        $date_stamp = gmdate( 'Ymd', $timestamp );

        $credential_scope = $date_stamp . '/' . $region . '/' . $service . '/aws4_request';

        // Query parameters for SigV4 presigned URL (must be sorted alphabetically by key)
        $query_params = [
            'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'    => $access_key . '/' . $credential_scope,
            'X-Amz-Date'          => $amz_date,
            'X-Amz-Expires'       => (string) $expires_seconds,
            'X-Amz-SignedHeaders' => 'host',
        ];
        ksort( $query_params );

        $canonical_query_parts = [];
        foreach ( $query_params as $k => $v ) {
            $canonical_query_parts[] = rawurlencode( $k ) . '=' . rawurlencode( $v );
        }
        $canonical_query = implode( '&', $canonical_query_parts );

        $canonical_uri = '/' . rawurlencode( $bucket_name ) . '/' . str_replace( '%2F', '/', rawurlencode( $object_key ) );
        $canonical_headers = "host:" . $host . "\n";
        $signed_headers = 'host';
        $payload_hash = 'UNSIGNED-PAYLOAD';

        $canonical_request = "PUT\n" . $canonical_uri . "\n" . $canonical_query . "\n" . $canonical_headers . "\n" . $signed_headers . "\n" . $payload_hash;

        $algorithm = 'AWS4-HMAC-SHA256';
        $string_to_sign = $algorithm . "\n" . $amz_date . "\n" . $credential_scope . "\n" . hash( 'sha256', $canonical_request );

        $k_secret  = 'AWS4' . $secret_key;
        $k_date    = hash_hmac( 'sha256', $date_stamp, $k_secret, true );
        $k_region  = hash_hmac( 'sha256', $region, $k_date, true );
        $k_service = hash_hmac( 'sha256', $service, $k_region, true );
        $k_signing = hash_hmac( 'sha256', 'aws4_request', $k_service, true );
        $signature = hash_hmac( 'sha256', $string_to_sign, $k_signing );

        $upload_url = 'https://' . $host . $canonical_uri . '?' . $canonical_query . '&X-Amz-Signature=' . $signature;

        $final_public_url = ! empty( $public_url ) ? $public_url . '/' . $object_key : 'https://' . $host . '/' . $bucket_name . '/' . $object_key;

        return [
            'success'    => true,
            'upload_url' => $upload_url,
            'public_url' => $final_public_url,
            'key'        => $object_key,
            'filename'   => $file_name,
        ];
    }
}
