<?php
/**
 * Owner email resolver trait file.
 *
 * Shared helper used by App background jobs to resolve the email
 * address(es) of a resource owner — whether that owner is an
 * individual user or an organisation with one or more resource_owner
 * members.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Apps
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Apps;

use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\OwnerSubjects\Organization;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Resolves one or more email addresses from a resource owner ID.
 *
 * Individual owners   → single email from the linked User.
 * Organisation owners → emails from all members holding the
 *                       'resource_owner' role slug.
 */
trait OwnerEmailResolverTrait {

    /**
     * Resolve all notification email addresses for a given owner ID.
     *
     * Returns an empty array when the owner does not exist, is a
     * platform owner (no direct email), or has no contactable members.
     *
     * @param int $owner_id The owner ID from the app's owner_id column.
     * @return string[]     Valid email addresses to notify.
     */
    private function resolve_owner_emails( int $owner_id ): array {
        if ( $owner_id <= 0 ) {
            return [];
        }

        $owner = Owner::get_by_id( $owner_id );

        if ( ! $owner || ! $owner->exists() ) {
            return [];
        }

        // Individual owner — single user email.
        if ( $owner->is_individual() ) {
            $user  = User::get_by_id( $owner->get_subject_id() );
            $email = $user?->get_email() ?? '';

            return filter_var( $email, FILTER_VALIDATE_EMAIL )
                ? [ $email ]
                : [];
        }

        // Organisation owner — notify all resource_owner members.
        if ( $owner->is_organization() ) {
            $org     = Organization::get_by_id( $owner->get_subject_id() );
            $members = $org?->get_members();
            $emails  = [];

            if ( ! $members ) {
                return [];
            }

            foreach ( $members as $member ) {
                $role = $member->get_role();

                if ( $role?->get_slug() !== 'resource_owner' ) {
                    continue;
                }

                $email = $member->get_user()->get_email();

                if ( filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
                    $emails[] = $email;
                }
            }

            return $emails;
        }

        // Platform owner — no direct email contact.
        return [];
    }
}