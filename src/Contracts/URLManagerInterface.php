<?php
/**
 * The URL manager interface.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */
namespace SmartLicenseServer\Contracts;

interface URLManagerInterface {
    public const LOGIN_URL_PREFIX_KEY               = 'login_url_prefix';
    public const LOGOUT_URL_PREFIX_KEY              = 'logout_url_prefix';
    public const ADMIN_URL_PREFIX_KEY               = 'admin_url_prefix';
    public const CLIENT_DASHBOARD_URL_PREFIX_KEY    = 'client_dashboard_url_prefix';
    public const REPOSITORY_URL_PREFIX_KEY          = 'repository_url_prefix';
    public const DOWNLOADS_URL_PREFIX_KEY           = 'downloads_url_prefix';
    public const UPLOADS_URL_PREFIX_KEY             = 'uploads_url_prefix';

    
}