<?php
/**
 * Extension to MIME type map.
 * Used primarily for setting the 'Content-Type' HTTP header when serving a file
 * based on its known file extension.
 *
 * NOTE: This map is NOT suitable for file upload security. For secure file
 * validation, always use PHP's finfo extension (binary signature detection).
 *
 * @package SmartLicenseServer\Helpers
 * @author Callistus
 */

return [
    // --- Images ---
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'jpe'  => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'svg'  => 'image/svg+xml',
    'webp' => 'image/webp',
    'ico'  => 'image/x-icon',
    'tiff' => 'image/tiff',
    'tif'  => 'image/tiff',
    'bmp'  => 'image/bmp',
    'heic' => 'image/heic',
    'heif' => 'image/heif',
    'avif'  => 'image/avif',
    'heics' => 'image/heic-sequence',
    'heifs' => 'image/heif-sequence',

    // --- Documents and Text ---
    'pdf'  => 'application/pdf',
    'json' => 'application/json',
    'xml'  => 'application/xml',
    'txt'  => 'text/plain',
    'log'  => 'text/plain',
    'csv'  => 'text/csv',
    'tsv'  => 'text/tab-separated-values',
    'html' => 'text/html',
    'htm'  => 'text/html',
    'css'  => 'text/css',
    'js'   => 'application/javascript',
    'yaml' => 'application/x-yaml', // Added for config files
    'yml'  => 'application/x-yaml', // Alias for yaml

    // --- Microsoft Office Documents ---
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'mdb'  => 'application/vnd.ms-access',
    'mpp'  => 'application/vnd.ms-project',
    'docm' => 'application/vnd.ms-word.document.macroEnabled.12',
    'dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
    'dotm' => 'application/vnd.ms-word.template.macroEnabled.12',
    'xlsm' => 'application/vnd.ms-excel.sheet.macroEnabled.12',
    'xlsb' => 'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
    'xltx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
    'xltm' => 'application/vnd.ms-excel.template.macroEnabled.12',
    'xlam' => 'application/vnd.ms-excel.addin.macroEnabled.12',
    'pptm' => 'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
    'ppsx' => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
    'ppsm' => 'application/vnd.ms-powerpoint.slideshow.macroEnabled.12',
    'potx' => 'application/vnd.openxmlformats-officedocument.presentationml.template',
    'potm' => 'application/vnd.ms-powerpoint.template.macroEnabled.12',
    'ppam' => 'application/vnd.ms-powerpoint.addin.macroEnabled.12',
    'sldx' => 'application/vnd.openxmlformats-officedocument.presentationml.slide',
    'sldm' => 'application/vnd.ms-powerpoint.slide.macroEnabled.12',
    'odg'  => 'application/vnd.oasis.opendocument.graphics',
    'odf'  => 'application/vnd.oasis.opendocument.formula',
    'odb'  => 'application/vnd.oasis.opendocument.database',


    // --- OpenDocument Formats (ODF) ---
    'odt'  => 'application/vnd.oasis.opendocument.text',
    'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
    'odp'  => 'application/vnd.oasis.opendocument.presentation',

    // --- Compressed Archives ---
    'zip'  => 'application/zip',
    'rar'  => 'application/vnd.rar',
    '7z'   => 'application/x-7z-compressed',
    'tar'  => 'application/x-tar', // Added
    'gz'   => 'application/gzip',
    'tgz'  => 'application/gzip',
    'bz2'  => 'application/x-bzip2',
    'dmg'  => 'application/x-apple-diskimage', // Added

    // --- Audio ---
    'mp3'  => 'audio/mpeg',
    'm4a'  => 'audio/x-m4a',
    'wav'  => 'audio/wav',
    'ogg'  => 'audio/ogg',
    'oga'  => 'audio/ogg',
    'flac' => 'audio/flac',

    // --- Video ---
    'mp4'  => 'video/mp4',
    'm4v'  => 'video/mp4',
    'mov'  => 'video/quicktime',
    'wmv'  => 'video/x-ms-wmv',
    'avi'  => 'video/x-msvideo',
    'webm' => 'video/webm',
    '3gp'  => 'video/3gpp',
    'flv'  => 'video/x-flv', // Added
    'mkv'  => 'video/x-matroska', // Added

    // --- Fonts ---
    'ttf'  => 'font/ttf',
    'otf'  => 'font/otf',
    'woff' => 'font/woff',
    'woff2'=> 'font/woff2',
    'eot'  => 'application/vnd.ms-fontobject', // Added

    // --- Programming & Scripts ---
    'php'  => 'application/x-httpd-php', // Common for direct serving
    'sh'   => 'application/x-sh', // Added
    'pl'   => 'application/x-perl', // Added
    'py'   => 'application/x-python', // Added
    'sql'  => 'application/x-sql', // Added
    'rb'   => 'application/x-ruby', // Added
    'c'    => 'text/x-c', // Added
    'h'    => 'text/x-chdr', // Added

    // --- Other / System Files ---
    'bin'  => 'application/octet-stream',
    'exe'  => 'application/octet-stream',
    'dll'  => 'application/x-msdownload', // More specific executable/system file type
    'iso'  => 'application/x-iso9660-image', // Added

    'rtf'  => 'application/rtf',
    'md'   => 'text/markdown',
    'ini'  => 'text/plain',
    'conf' => 'text/plain',
    'bat'  => 'application/x-msdos-program',
    'svgz' => 'image/svg+xml', // compressed SVG
    'xpi'  => 'application/x-xpinstall', // Firefox extension
    'crx'  => 'application/x-chrome-extension',

    'apk'  => 'application/vnd.android.package-archive',
    'aab'  => 'application/vnd.android.package-archive',
    'ipa'  => 'application/octet-stream',

    'cpp'   => 'text/x-c++src',
    'java'  => 'text/x-java-source',
    'json5' => 'application/json',
    'jsx'   => 'text/jsx',
    'tsx'   => 'text/tsx',
    'vue'   => 'text/x-vue',
];