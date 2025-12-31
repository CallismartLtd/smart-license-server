<?php
/**
 * Hosted Software class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\HostedApps.
 */

namespace SmartLicenseServer\HostedApps;

use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\FileSystem\SoftwareRepository;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\Monetization\Monetization;
use SmartLicenseServer\Utils\CommonQueryTrait;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

/**
 * Represents a hosted software in the repository.
 * 
 * A hosted software is any other application type that is not a plugin or theme.
 */
class Software extends AbstractHostedApp {
    use SanitizeAwareTrait, CommonQueryTrait;
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
     * Software cover URL
     * 
     * @var string
     */
    protected $cover = '';

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
        foreach ( $this->get_icons() as $icon ) {
            if ( ! empty( $icon ) ) {
                return $icon;
            }
        }

        return smliser_get_placeholder_icon( $this->get_type() );
    }

    /**
     * Get cover image
     * 
     * @return string
     */
    public function get_cover() : string {
        return $this->cover;
    }

    /**
     * Get author profile
     * 
     * @return string
     */
    public function get_author_profile() : string {
        return $this->author;
    }

    /*
    |-------------
    | SETTERS
    |-------------
    */

    /**
     * Set software cover image
     * 
     * @param string $cover
     */
    public function set_cover( $cover ) {
        $this->cover = self::sanitize_web_url( $cover );
    }

    /*
    |--------------------
    | CRUD METHODS
    |--------------------
    */

    /**
     * Get the software by ID
     * 
     * @param int $id The software ID.
     * @return self|null
     */
    public static function get_software( $id ) : ?self {
        return self::get_self_by_id( $id, self::TABLE );
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
        $repo_class = new SoftwareRepository;

        $software_data = [
            'name'          => $this->get_name(),
            'author'        => $this->get_author(),
            'status'        => $this->get_status(),
            'download_link' => $this->get_download_url(),
            'updated_at'    => \gmdate( 'Y-m-d H:i:s' ),
        ];

        if ( $this->get_id() ) {

            if ( is_array( $file ) ) {
                $slug = $repo_class->upload_zip( $file, $this->get_slug(), true );
                
                if ( is_smliser_error( $slug ) ) {
                    return $slug;
                }

                if ( $this->get_slug() !== $slug ) {
                    $software_data['slug'] = $slug;

                    $this->set_slug( $slug );
                }
            }


            $result = $db->update( $table, $software_data, [ 'id' => $this->get_id() ] );
        } else {

            if ( ! is_array( $file ) ) {
                return new Exception( 'no_file_provided', 'No software file provided for new software.', ['status' => 400] );
            }

            $slug = $repo_class->upload_zip( $file, $filename, false );

            if ( is_smliser_error( $slug ) ) {
                return $slug;
            }

            $software_data['slug']          = $slug;
            $software_data['created_at']    = \gmdate( 'Y-m-d H:i:s' );

            $result = $db->insert( $table, $software_data );

            $this->set_id( $db->get_insert_id() );
        }

        $repo_class->regenerate_app_dot_json( $this );

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
        $self = new static();

        $self->set_id( $result['id'] ?? 0 );
        $self->set_name( $result['name'] ?? '' );
        $self->set_slug( $result['slug'] ?? '' );
        $self->set_author( $result['author'] ?? '' );
        $self->set_author_profile( $result['author_profile'] ?? '' );
        $self->set_status( $result['status'] ?? self::STATUS_ACTIVE );
        $self->set_download_url( $result['download_link'] ?? '' );
        $self->set_created_at( $result['created_at'] ?? '' );
        $self->set_last_updated( $result['last_updated'] ?? '' );

        $repo_class = new SoftwareRepository();

        $app_json   = $repo_class->get_app_dot_json( $self );

        $self->set_manifest( $app_json );
        $self->set_version( $app_json['version'] ?? '' );
        $self->set_tags( $app_json['tags'] ?? [] );

        $self->set_screenshots( $repo_class->get_assets( $self->get_slug(), 'screenshots' ) );
        $self->set_cover( $repo_class->get_assets( $self->get_slug(), 'cover' ) );
        $self->set_icons( $repo_class->get_assets( $self->get_slug(), 'icons' ) );

        



        return $self;
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