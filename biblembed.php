  <?php

  /*
   * Plugin Name: Biblembed
   * Plugin URI: https://github.com/vincenzo/biblembed
   * Description: This Plugin allows to embed Bible passages using Bible Gateway.
   * Version: 0.1
   * Author: Vincenzo Russo
   * Author URI: http://neminis.org
   *
   * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 3 of the License, or (at your option) any later version.
   * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Library General Public License for more details.
   * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
   *
   ***/

  /**
   *  Handler for parsing the shortcode.
   *
   * @param array $atts
   *  Attributes of the shortcode.
   * @return string
   *  The verse.
   **/
  function biblembed_shortcode_handler($atts) {
    // Set default values.
    $atts = shortcode_atts(array(
      'version' => 'NIVUK',
      'type' => 'quote', // quote or link
      'verse' => 'John 1:1'
    ), $atts);

    if ($atts['type'] === 'quote') {
      return biblembed_get_verse_quote($atts);
    }
    else {
      return biblembed_get_verse_link($atts);
    }
  }

  /**
   * Get the quotation for the verse.
   *
   * @param array $atts
   *  Shortcode attributes.
   * @return string
   *  The quotation.
   */
  function biblembed_get_verse_quote($atts) {
    $url = sprintf("http://www.biblegateway.com/passage/?search=%s&version=%s", urlencode($atts['verse']), $atts['version']);

    $doc = new DOMDocument();
    $doc->loadHTMLFile($url);

    $xquery = sprintf("//div[contains(normalize-space(@class), 'passage') and contains(normalize-space(@class), 'version-%s')]", $atts['version']);

    $xdoc = new DOMXPath($doc);
    $passages = $xdoc->query($xquery);

    $passage = new DOMDocument();
    $passage->appendChild($passage->importNode($passages->item(0), TRUE));

    return $passage->saveHTML() .  "<span>&mdash; " . _(biblembed_get_verse_link($atts) . " &bull; Extracted from BibleGateway.") . "</span>";
  }

  /**
   * Get the link for the verse.
   *
   * @param array $atts
   *  Shortcode attributes.
   * @return string
   *  The link.
   */
  function biblembed_get_verse_link($atts, $anchor_text = NULL) {
    return sprintf('<a href="%s">%s</a>',
      sprintf("http://www.biblegateway.com/passage/?search=%s&version=%s", urlencode($atts['verse']), $atts['version']),
      $anchor_text ? $anchor_text : $atts['verse']);
  }

  add_shortcode('bible', 'biblembed_shortcode_handler');
