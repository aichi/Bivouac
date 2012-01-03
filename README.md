Bivouac
=======

Bivouac is a PDF generator for Basecamp calendar. It is possible to choose from projects list one project and than optionally date interval.

Setup
-----

This tool is possible to setup by `config.php`.

### Access to Basecamp Project

It is needed to write Basecamp API key and project URL into `config.php`. API key for every Basecamp project is on your My info page under "Show your tokens" link. (http://help.37signals.com/basecamp/questions/405-how-do-i-enable-the-api) Copy this API key into `config.php`. You have to also update URL constant with url to your project. It also depends on used protocol HTTP or HTTPS.

### Default Basecamp Font

Bivouac is using "Lucia Grande" font like original Basecamp by default. This font should be found in your OS font folder or you have to buy it for commercial usage. You can read more here: (http://spencerlavery.com/blog/calibri-for-mac-lucida-grande-for-windows/).

When you have LuciaGrande.ttf or other font which you would like to use use this page (http://eclecticgeek.com/dompdf/load_font.php) to generate proper font metrics. This step is especialy needed when you want to render Unicode texts. Than please follow this manual page: (http://code.google.com/p/dompdf/wiki/CPDFUnicode).

When you are not sure which font you should use point your browser to [Liberation Fonts](http://en.wikipedia.org/wiki/Liberation_fonts).

Bivouac is coming with pre-generated font metrics for Lucia Grande font. They are located in `lib\dompdf\lib\fonts`.

You can also switch easily to other font changing `FONT`, `HEADER_FONT` and `FOOTER_FONT` constants.

Used Libraries
--------------

Bivouac uses this libraries:

 * [DOMPDF](http://code.google.com/p/dompdf/) 0.6b2 under LGPL 2.1 licence
 * [Basecamp PHP API](http://code.google.com/p/basecamp-php-api/) under LGPL 2.1 licence (with added method http://code.google.com/p/basecamp-php-api/issues/detail?id=4)
 * Javascript library [JAK](http://jak.seznam.cz) under MIT licence


Licence
-------

This tool is licenced under [LGPL 3](http://www.gnu.org/licenses/lgpl.html).

