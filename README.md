odtphp
======

### Original description

OdtPHP is a library to quickly generate Open Document Text-files that can be read by a [gigantic set][3] of Office Suites, including LibreOffice, OpenOffice and even Microsoft Office from PHP code. It uses a simple templating mechanism.
See the tests/ folder for a set of examples.

This repository already includes the changes suggested by [Vikas Mahajan][1] and a number of other bug fixes.

### LibreOffice / OpenDocument compatibility

Since version 4.x, the default ZIP handler is `Odtphp\Zip\PhpZipProxy` (PHP `ext-zip`), which is now
a required extension (`ext-zip` in `composer.json`). This fixes documents generated from ODT templates
produced by LibreOffice >= 25 being flagged as "corrupted" and requiring a repair on open: the bundled
`PclZipProxy` used to leave a stale ZIP "data descriptor" flag when rewriting archives that contain
entries written with a data descriptor (as LibreOffice >= 25 does), producing a structurally invalid
archive that older/lenient readers tolerated but newer ones reject.

`PclZipProxy` remains available and has been fixed for this specific issue; it can still be selected
explicitly via the `ZIP_PROXY` configuration option for platforms without `ext-zip`.

### History

This project was initially started by Julien Pauli, Olivier Booklage, Vincent Brouté and published at [http://www.odtphp.com][2] (link leads to archived version of page, as it is not available any longer).

### Links:

* http://sourceforge.net/projects/odtphp/ Sourceforge Project of the initial library (stale)

[1]: http://vikasmahajan.wordpress.com/2010/12/09/odtphp-bug-solved/
[2]: https://web.archive.org/web/20120531095719/http://www.odtphp.com/index.php?i=home
[3]: https://en.wikipedia.org/wiki/OpenDocument_software#Text_documents_.28.odt.29