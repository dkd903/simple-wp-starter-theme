# htmltowordpress.io Simple WordPress Starter Theme
All the themes generated with htmltowordpress.io consist of two parts, the original html converted to php format and the Simple WordPress Starter Theme. 

## Files
- __front-page.php__ contains your front-page (index.html) after the conversion
- __index.php__ contains the default-template for the theme and is empty if no default.html is added during the conversion.
- __functions.php__ loads the files located inside the html2wp folder. It is recommended to not add any custom code here if you plan to convert your theme again. Please consider using a child theme instead: http://codex.wordpress.org/Child_Themes
- __style.css__ provides details about the theme that are used by WordPress
- __html2wp-folder__ contains the theme functionality logic
- __composer.json__ & __composer.lock__ are used by the composer package manager for dependency management
- __Any other files__ are added from the original html website during the conversion to a WordPress theme.

## Contributing
The source and the issues tracker for the Simple WordPress Starter Theme cand be found from: https://github.com/html2wp/simple-wp-starter-theme

## Copyrights
The files located inside the html2wp folder (excluding the html2wp/vendor folder) and other files that are part of the
Simple WordPress Starter Theme (https://github.com/html2wp/simple-wp-starter-theme) are licensed under the GPLv2+.

Ownership of any other files and their copyrights belong to their respective authors.