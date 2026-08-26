<?php
/**
 * The Security Guard class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security\Context
 */

namespace SmartLicenseServer\Security\Context;

/**
 * Orchestrates the transition from an authenticated Actor to a contextual Principal.
 * The Guard is responsible for resolving the relationship between the Actor 
 * and the specific Resource Owner targeted by a Request.
 */
final class Guard {

    /**
     * The current principal instance for this request.
     * 
     * @var Principal|null
     */
    private ?Principal $current_principal = null;

    /**
     * Set the current principal.
     *
     * @param Principal|null $principal
     * @return void
     */
    public function set_principal( ?Principal $principal ): void {
        $this->current_principal = $principal;
    }

    /**
     * Get the current principal.
     *
     * @return Principal|null
     */
    public function get_principal(): ?Principal {
        return $this->current_principal;
    }

    /**
     * Get the current principal.
     *
     * @return Principal
     * @throws \RuntimeException When no pricipal is bound, use ::has_principal() first.
     */
    public function principal(): Principal {
        if ( null === $this->current_principal ) {
            throw new \RuntimeException( 'No active Principal bound to Guard.' );
        }

        return $this->current_principal;
    }

    /**
     * Check if a principal is currently set.
     *
     * @phpstan-assert-if-true Principal $this->get_principal()
     * @psalm-assert-if-true Principal $this->get_principal()
     * @phpstan-assert-if-true Principal $this->current_principal
     *
     * @return bool
     */
    public function has_principal(): bool {
        return null !== $this->current_principal;
    }

    /**
     * Clear the principal (useful for testing or ending request).
     *
     * @return void
     */
    public function clear_principal(): void {
        $this->current_principal = null;
    }
}