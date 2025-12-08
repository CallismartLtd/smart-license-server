<?php
/**
 * MIME type → preferred file extension map.
 *
 * This map is used by the FileSystemHelper to determine the *safe* file extension
 * based on the file's binary signature (MIME type).
 *
 * @package SmartLicenseServer\FileSystem\FileSystem
 * @author Callistus
 */

return [

    // --- Images ---
    'image/jpeg'                    => 'jpg',
    'image/pjpeg'                   => 'jpg', // IE/Legacy variant
    'image/png'                     => 'png',
    'image/gif'                     => 'gif',
    'image/webp'                    => 'webp',
    'image/bmp'                     => 'bmp',
    'image/x-icon'                  => 'ico',
    'image/svg+xml'                 => 'svg',
    'image/tiff'                    => 'tif',
    'image/heif'                    => 'heif',
    'image/heic'                    => 'heic',
    'image/x-bmp'                   => 'bmp',
    'image/x-ms-bmp'                => 'bmp',
    'image/jpg'                     => 'jpg', // legacy variant
    'image/avif'                    => 'avif', // modern image format
    'video/avi'                     => 'avi', // fallback for some systems


    // --- Documents ---
    'application/pdf'               => 'pdf',
    'application/msword'            => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-excel'      => 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    'application/vnd.ms-powerpoint' => 'ppt',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    'text/plain'                    => 'txt',
    'text/csv'                      => 'csv',
    'text/tab-separated-values'     => 'tsv',
    'application/rtf'               => 'rtf',
    'application/json'              => 'json',
    'application/xml'               => 'xml',
    'text/xml'                      => 'xml',
    'application/x-yaml'            => 'yaml',

    // --- Archives (Expanded for Software Distribution) ---
    'application/zip'               => 'zip',
    'application/x-zip-compressed'  => 'zip',
    'application/x-tar'             => 'tar',
    'application/gzip'              => 'gz',
    'application/x-gzip'            => 'gz',
    'application/x-bzip2'           => 'bz2',
    'application/x-7z-compressed'   => '7z',
    'application/x-rar-compressed'  => 'rar',
    'application/vnd.rar'           => 'rar',
    'application/x-apple-diskimage' => 'dmg',
    'application/x-iso9660-image'   => 'iso',
    
    // --- Audio ---
    'audio/mpeg'                    => 'mp3',
    'audio/wav'                     => 'wav',
    'audio/ogg'                     => 'ogg',
    'audio/x-flac'                  => 'flac',
    'audio/aac'                     => 'aac',
    'audio/webm'                    => 'webm',
    'audio/mp4'                     => 'm4a',

    // --- Video ---
    'video/mp4'                     => 'mp4',
    'video/x-msvideo'               => 'avi',
    'video/x-ms-wmv'                => 'wmv',
    'video/mpeg'                    => 'mpeg',
    'video/webm'                    => 'webm',
    'video/ogg'                     => 'ogv',
    'video/3gpp'                    => '3gp',
    'video/quicktime'               => 'mov',
    'video/x-flv'                   => 'flv',

    // --- Code & Scripts ---
    'text/html'                     => 'html',
    'application/xhtml+xml'         => 'xhtml',
    'text/css'                      => 'css',
    'text/javascript'               => 'js',
    'application/javascript'        => 'js',
    'application/x-httpd-php'       => 'php',
    'text/x-php'                    => 'php',
    'application/x-sh'              => 'sh',
    'application/x-perl'            => 'pl',
    'application/x-python'          => 'py',

    // --- Fonts ---
    'font/otf'                      => 'otf',
    'font/ttf'                      => 'ttf',
    'font/woff'                     => 'woff',
    'font/woff2'                    => 'woff2',
    'application/vnd.ms-fontobject' => 'eot', // Embedded OpenType

    // --- Executables / System Files ---
    'application/octet-stream'      => 'bin', // Generic binary data (often default)
    'application/x-msdownload'      => 'exe', // Windows executable
    'application/x-dosexec'         => 'exe', // DOS executable
    'application/vnd.android.package-archive' => 'apk', // Android Package

    // --- Vendor/WordPress Specific ---
    'application/x-wordpress-plugin' => 'zip',
    'application/x-wordpress-theme'  => 'zip',
    'application/vnd.mozilla.xul+xml' => 'xul', // Mozilla/Firefox specific
    'application/x-shockwave-flash' => 'swf', // Flash

    // --- Additional Useful MIME Types ---
    'text/markdown'                 => 'md',
    'text/x-log'                    => 'log',
    'application/x-msi'             => 'msi',
    'application/vnd.apple.installer+xml' => 'pkg', // macOS installer
    'application/x-x509-ca-cert'    => 'crt',
    'application/pkix-cert'         => 'cer',
    'application/x-pem-file'        => 'pem',
    'application/x-openssl-key'     => 'key',
    'application/x-rpm'             => 'rpm',
    'application/x-deb'             => 'deb',
    'application/wasm'              => 'wasm', // WebAssembly
    'application/manifest+json'     => 'webmanifest', 


];