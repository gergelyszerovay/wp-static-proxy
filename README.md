# WP Static Proxy

WP Static Proxy (WPSP) converts and hosts your Wordpress website as a static website. 

## Features

Serving website as a static site has the following advantages:

- Improved page speed
- More secure, act as an additional security layer
- Privacy friendliness, easier GDPR compliance without the "Cookie bar"

Additional features of WPSP:

- optimizes the static website for maximum Google Page Speed and Gtmetrix performance
- compresses and optimizes PNG, JPEG and GIF files on the fly
- stores HTML, XML, CSS, JS, SVG, ICO, TTF, OTF and EOT files pre-compressed, and it serves them compressed
- sets cache expiration headers
- caches files, 404 errors and HTTP 301/302 redirects

## Website architecture

In order to use WPSP, you need two hosting accounts:

- One for your Wordpress site, eg.: https://backend.example.com
- And one for WPSP and the static website, eg.: https://example.com

Additionally, you can add a CDN to the static website to reduce the load on your hosting accounts.

Your domain name (example.com) points to the static website, WPSP automatically downloads and caches the requested webpages from your Wordpress site. After you've made changes in the content of the website, change the contents of the Wordpress website, clear WPSP’s cache using its admin interface. 

You can password-protect the entire Wordpress website (https://backend.example.com, both the admin and the public parts) with a [.htaccess/.htpasswd](https://www.web2generators.com/apache-tools/htpasswd-generator) file pair, so only you and the WPSP on the static site can access your Wordpress website.

## How to install WPSP?

To install WPSP, copy the "config-sample.php", “cache.php" and "htaccess-template.txt" files into the "public_html" folder of your example.com domain. Make a copy of the "config-sample.php" file named "config.php", then edit the "config.php" file, set the following values:

```
$cacheUrl = '//example.com';
$originUrl = '//backend.example.com';
```

```
'adminKey' => '[your admin key, it can contain only letters and numbers]'
'adminPassword’ => '[your admin password]'
'username' => '[the username for backend.example.com]' 
'password' => '[the password for backend.example.com]' 
```

Optionally you can change the location of the cache-log folder.

To start WPSP, enter the following URL into your web browser:

https://example.com/cache.php?a=htaccess

WPSP generates a .htaccess file based on your config.php

The .htaccess file forces HTTPS connection and also redirects www.example.com to example.com. It also redirects the Wordpress search queries to the https://example.com/search/ page. You can change this behavior by manually editing the .htaccess file.

## Admin interface

You can access WPSP’s admin interface by using this URL:

https://example.com/cache-admin/[your admin key]/

On the admin interface you can:

- Delete HTML files, cached redirects and 404 errors
- Clear all content from the cache

## How to protect your visitors' privacy?

Disclaimer: I’m a developer, not a lawyer

1. Don’t use cookies. When you use WPSP, your website doesn’t send cookies to your visitor’s web browser.

2. Remove external content from your site. External websites can track your visitors and send them cookies. When there is no external content in your website, there are no cookies, so you don’t need a “cookie bar“. The most commonly used external content is web fonts. You can use the [OMGF plugin](https://wordpress.org/plugins/host-webfonts-local/) to serve Google Fonts from your website. 

3. Hosted search solutions usually also use external websites, so they can also track your visitors and send them cookies. You can use my WP Static Search plugin on less complex, it is completely browser based (uses [lunr.js](https://lunrjs.com/)), so it doesn’t use external webservices.

4. Use privacy-friendly analytics, for example Simple Analytics

## Additional plugins for maximum performance

I already mentioned the [OMGF](https://wordpress.org/plugins/host-webfonts-local/) plugin for downloading Google Web Fonts, and my WP Static Search plugin.

With WPSP, you don’t need an additional Wordpress cache. I use [Autoptimize](https://wordpress.org/plugins/autoptimize/) to optimize the HTML, CSS and JS files with the following settings: 

- JS, CSS & HTML tab: "Optimize JavaScript Code?", " Aggregate JS-files?", "Optimize CSS Code?", "Aggregate CSS-files?", "Also aggregate inline CSS?", "Inline all CSS?", " Optimize HTML Code?", "Save aggregated script/css as static files?", "Minify excluded CSS and JS files?", "Also optimize for logged in editors/ administrators?"
- Images tab: "Lazy-load images?"
 "Google Fonts - Combine and preload in head (fonts load late, but are not render-blocking), includes display:swap.- Extra tab:", "Removes WordPress’ core emojis’ inline CSS, inline JavaScript, and an otherwise un-autoptimized JavaScript file."

To auto-generate WEBP images from JPEGs and PNGs, I use the [WebP Express plugin](https://wordpress.org/plugins/webp-express/) with these settings: 

- Operation mode: “Varied image responses"
- Scope: "uploads and themes"
- Destination folder: "In separate folder"
- Destination structure: "Document root"
- Cache-Control header: "Do not set"
- “Enable direct redirection to existing converted images?"
- "Enable redirection to converter? "
- "Create webp files upon request?"
- " Alter HTML?"
- "Replace <img> tags with <picture> tags, adding the webp to srcset."
- "Dynamically load picturefill.js on older browsers"
- "Reference webps that haven’t been converted yet"
- Where to replace: "The complete page (using output buffering)"

