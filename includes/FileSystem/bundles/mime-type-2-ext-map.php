<?php
/**
 * MIME type to preferred file extension map (2026 Updated).
 *
 * This map is used by the FileSystemHelper to determine the *safe* file extension
 * based on the file's binary signature (MIME type).
 *
 * Updated for 2026: Removed legacy variants, added modern formats (AVIF, WebP video),
 * consolidated duplicates, and organized by category.
 *
 * @package SmartLicenseServer\FileSystem\bundles
 * @author Callistus Nwachukwu
 * @updated 2026
 */

return [

    // --- Images (Modern & Legacy) ---
    'image/jpeg'                    => 'jpg',
    'image/png'                     => 'png',
    'image/gif'                     => 'gif',
    'image/webp'                    => 'webp',
    'image/avif'                    => 'avif',
    'image/bmp'                     => 'bmp',
    'image/x-icon'                  => 'ico',
    'image/svg+xml'                 => 'svg',
    'image/tiff'                    => 'tif',
    'image/heif'                    => 'heif',
    'image/heic'                    => 'heic',

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
    'text/markdown'                 => 'md',
    'application/rtf'               => 'rtf',
    'application/json'              => 'json',
    'application/xml'               => 'xml',
    'text/xml'                      => 'xml',
    'application/x-yaml'            => 'yaml',

    // --- OpenDocument Formats ---
    'application/vnd.oasis.opendocument.text'           => 'odt',
    'application/vnd.oasis.opendocument.spreadsheet'    => 'ods',
    'application/vnd.oasis.opendocument.presentation'   => 'odp',
    'application/vnd.oasis.opendocument.graphics'       => 'odg',
    'application/vnd.oasis.opendocument.formula'        => 'odf',
    'application/vnd.oasis.opendocument.database'       => 'odb',

    // --- Archives (Software Distribution) ---
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

    // --- Video (Including WebP/WebM Modern Formats) ---
    'video/mp4'                     => 'mp4',
    'video/webm'                    => 'webm',
    'video/x-msvideo'               => 'avi',
    'video/x-ms-wmv'                => 'wmv',
    'video/mpeg'                    => 'mpeg',
    'video/ogg'                     => 'ogv',
    'video/3gpp'                    => '3gp',
    'video/quicktime'               => 'mov',
    'video/x-flv'                   => 'flv',
    'video/x-matroska'              => 'mkv',

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
    'text/x-c++'                    => 'cpp',
    'text/x-java-source'            => 'java',
    'application/typescript'        => 'ts',
    'text/jsx'                      => 'jsx',
    'text/tsx'                      => 'tsx',
    'text/x-vue'                    => 'vue',
    'text/x-go'                     => 'go',
    'text/x-rust'                   => 'rs',
    'application/x-sql'             => 'sql',

    // --- Fonts ---
    'font/otf'                      => 'otf',
    'font/ttf'                      => 'ttf',
    'font/woff'                     => 'woff',
    'font/woff2'                    => 'woff2',
    'application/vnd.ms-fontobject' => 'eot',

    // --- Executables & System Files ---
    'application/octet-stream'      => 'bin',
    'application/x-msdownload'      => 'exe',
    'application/x-msdos-program'   => 'exe',
    'application/vnd.android.package-archive' => 'apk',
    'application/x-rpm'             => 'rpm',
    'application/x-deb'             => 'deb',

    // --- Web & Mobile ---
    'application/x-wordpress-plugin' => 'zip',
    'application/x-wordpress-theme'  => 'zip',
    'application/manifest+json'     => 'webmanifest',
    'application/wasm'              => 'wasm',
    'application/x-xpinstall'       => 'xpi',
    'application/x-chrome-extension' => 'crx',

    // --- Certificates & Keys ---
    'application/x-x509-ca-cert'    => 'crt',
    'application/pkix-cert'         => 'cer',
    'application/x-pem-file'        => 'pem',
    'application/x-openssl-key'     => 'key',

    // --- Other ---
    'text/x-log'                    => 'log',
    'application/x-msi'             => 'msi',
    'application/vnd.apple.installer+xml' => 'pkg',
];