<?php
/**
 * Hosted Software class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\HostedApps.
 */

namespace SmartLicenseServer\HostedApps;

use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\Monetization\Monetization;
use SmartLicenseServer\FileSystem\ThemeRepository;

/**
 * Represents a hosted software in the repository.
 * 
 * A hosted software is any other application type that is not a plugin or theme.
 */
class Software extends AbstractHostedApp {
    /**
     * The database table for software.
     * 
     * @var string
     */
    const TABLE = \SMLISER_SOFTWARE_TABLE;

    /**
     * The software metadata table
     * 
     * @var string
     */
    const META_TABLE    = SMLISER_SOFTWARE_META_TABLE;

    /**
     * Icon url
     * 
     * @var string $icon_url
     */
    protected $icon_url = '';

    /**
     * Class constructor
     */
    public function __construct() {}

    /**
     * Get the application type.
     * 
     * @return string Application type.
     */
    public function get_type() : string {
        return 'software';
    }

    /**
     * Get the database table for software.
     * 
     * @return string Database table name.
     */
    public static function get_db_table() : string {
        return self::TABLE;
    }

    /**
     * Get the metadata table for software.
     * 
     * @return string Metadata table name.
     */
    public static function get_db_meta_table() : string {
        return self::META_TABLE;
    }

    /**
     * Get meta foriegn key.
     * 
     * @return string Foreign key column name.
     */
    protected function get_meta_foreign_key() : string {
        return 'software_id';
    }

    /**
     * Get the application icon URL.
     * 
     * @return string Icon URL.
     */
    public function get_icon() : string {
        if ( ! empty( $this->icon_url ) ) {
            return $this->icon_url;
        }

        return smliser_get_placeholder_icon( $this->get_type() );
    }

    /**
     * Get author profile
     * 
     * @return string
     */
    public function get_author_profile() : string {
        return $this->author;
    }

    /**
     * Save software to the database.
     * 
     * @return true|Exception True on success, exception on failure.
     */
    public function save() : true|Exception {
        $db         = smliser_dbclass();
        $table      = self::TABLE;

        $file       = $this->file;
        $filename   = strtolower( str_replace( ' ', '-', $this->get_name() ) );
        $repo_class = null; // No repository for software for now.

        $software_data = [
            'name'          => $this->get_name(),
            'author'        => $this->get_author(),
            'status'        => $this->get_status(),
            'download_link' => $this->get_download_url(),
            'updated_at'    => \gmdate( 'Y-m-d H:i:s' ),
        ];

        if ( $this->get_id() ) {

            if ( is_array( $file ) ) {
                // $slug = $repo_class->upload_zip( $file, $this->get_slug(), true );
                
                // if ( is_smliser_error( $slug ) ) {
                //     return $slug;
                // }

                // if ( $this->get_slug() !== $slug ) {
                //     $software_data['slug'] = $slug;

                //     $this->set_slug( $slug );
                // }
            }


            $result = $db->update( $table, $software_data, [ 'id' => $this->get_id() ] );
        } else {

            if ( ! is_array( $file ) ) {
                return new Exception( 'no_file_provided', 'No software file provided for new software.', ['status' => 400] );
            }

            // $slug = $repo_class->upload_zip( $file, $filename, false );

            // if ( is_smliser_error( $slug ) ) {
            //     return $slug;
            // }

            // $software_data['slug']          = $slug;
            $software_data['created_at']    = \gmdate( 'Y-m-d H:i:s' );

            $result = $db->insert( $table, $software_data );

            $this->set_id( $db->get_insert_id() );
        }

        // $repo_class->regenerate_app_dot_json( $this );

        return ( false !== $result ) ? true : new Exception( 'db_insert_error', $db->get_last_error() );
    }

    /**
     * Permanently delete a software.
     * 
     * @return bool True on success, false on failure.
     */
    public function delete() : bool {
        if ( ! $this->get_id() ) {
            return false;
        }

        $db         = smliser_dbclass();
        $table      = self::TABLE;
        $meta_table = self::META_TABLE;

        $meta_deleted       = $db->delete( $meta_table, [ 'software_id' => $this->get_id() ] );
        $software_deleted   = $db->delete( $table, [ 'id' => $this->get_id() ] );

        if ( ! empty( $this->meta_data ) ) {
            $this->meta_data = [];
        }

        return ( $software_deleted > 0 && $meta_deleted > 0 );
    }

    /*
    |---------------------
    | UTILITY METHODS
    |---------------------
    */

    /**
     * Converts associative array to object of this class.
     */
    public static function from_array( $result ) : static {
        $software = new static();

        $software->set_id( $result['id'] ?? 0 );
        $software->set_name( $result['name'] ?? '' );
        $software->set_slug( $result['slug'] ?? '' );
        $software->set_author( $result['author'] ?? '' );
        $software->set_status( $result['status'] ?? 'draft' );
        $software->set_download_url( $result['download_link'] ?? '' );

        



        return $software;
    }

    /**
     * Concrete implementation of rest response.
     * 
     * @return array
     */
    public function get_rest_response(): array {
        $data = array();

        if ( $this->is_monetized() ) {
            $monetization = Monetization::get_by_app( $this->get_type(), $this->get_id() );

            $data['monetization'] = $monetization->to_array();
        }

        return $data;
    }

}