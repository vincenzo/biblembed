<?php

/**
 * Plugin Name: BiblEmbed
 * Plugin URI: https://github.com/vincenzo/biblembed
 * Description: This Plugin allows to embed Bible passages using Bible Gateway.
 * Version: 0.2
 * Author: Vincenzo Russo
 * Author URI: http://artetecha.com
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 3 of the License, or (at your option) any later version.
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Library General Public License for more details.
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 ***/

/**
 * Default bibles per language.
 */
function biblembed_default_bibles($locale = 'und') {
  $defaults = array(
    'und' => 'NIV',
    'en_GB' => 'ESVUK',
    'en_US' => 'ESV',
    'it_IT' => 'NR2006',
  );
  return $defaults[$locale];
}

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
    'version' => biblembed_default_bibles(),
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
  // Get current post's ID.
  $post_id = $GLOBALS['wp_query']->post->ID;
  // Calculate the MD5 hash for this verse.
  $meta_hash = md5($atts['verse'] . $atts['version']);

  // If the verse has been previously cached.
  if ($bibleverse = get_post_meta($post_id, 'bible_' . $meta_hash, TRUE)) {
    // Get it from the cache.
    return $bibleverse;
  }

  // No cache found: query BibleGateway.
  $doc = new DOMDocument();
  $doc->loadHTMLFile(sprintf("http://www.biblegateway.com/passage/?search=%s&version=%s", urlencode($atts['verse']), $atts['version']));

  // XPath query to find the bible verses we asked for.
  $xdoc = new DOMXPath($doc);
  $passages = $xdoc->query(sprintf("//div[contains(normalize-space(@class), 'passage') and contains(normalize-space(@class), 'version-%s')]/p", $atts['version']));
  if ($passages->length === 0) {
    $passages = $xdoc->query(sprintf("//div[contains(normalize-space(@class), 'passage') and contains(normalize-space(@class), 'version-%s')]/div/p", $atts['version']));
  }

  // Initialise the output to return.
  $output = "<blockquote>";
  // Multiples verses that are meant to be rendered in a separated way are listed is a semicolon separated string.
  $verses = explode(";", $atts['verse']);

  // Render all the verses found.
  for ($i = 0; $i < $passages->length; $i++) {
    $passage = new DOMDocument();
    $passage->appendChild($passage->importNode($passages->item($i), TRUE));
    $output .= $passage->saveHTML();
    // Multiple verses can be rendered as one block. To check whether that's the case,
    // we check the number of verses obtained from a potential semicolon separated list with
    // the length of the list of passages.
    if (count($verses) == $passages->length) {
      $atts['verse'] = $verses[$i];
      $output .= "<br /><br />" . _(biblembed_get_verse_link($atts, TRUE)) . "</blockquote>";
      if ($passages->length - $i > 1) {
        $output .= '<hr /><blockquote>';
      }
    }
    else {
      if ($passages->length - $i == 1) {
        $output .= "<br /><br />" . _(biblembed_get_verse_link($atts)) . "</blockquote>";
      }
    }
  }

  // Remove all footnotes references.
  if (($footnotes = $xdoc->query('//div[@class="footnotes"]')) && ($footnotes->length > 0)) {
    for ($i = 0; $i < $footnotes->length; $i++) {
      $footnote = new DOMDocument();
      $footnote->appendChild($footnote->importNode($footnotes->item($i), TRUE));
      $output = str_ireplace(trim($footnote->saveHTML()), '', trim($output));
    }
  }

  // Remove all footnotes.
  if (($footnotes = $xdoc->query('//sup[@class="footnote"]')) && ($footnotes->length > 0)) {
    for ($i = 0; $i < $footnotes->length; $i++) {
      $footnote = new DOMDocument();
      $footnote->appendChild($footnote->importNode($footnotes->item($i), TRUE));
      $output = str_ireplace(trim($footnote->saveHTML()), '', trim($output));
    }
  }

  // Remove all footnotes.
  if (($verses = $xdoc->query('//sup[@class="versenum"]')) && ($verses->length > 0)) {
    for ($i = 0; $i < $verses->length; $i++) {
      $footnote = new DOMDocument();
      $footnote->appendChild($footnote->importNode($verses->item($i), TRUE));
      $output = str_ireplace(trim($footnote->saveHTML()), '', trim($output));
    }
  }

  $output = strip_tags($output, '<blockquote><a><br><hr>');

  // Cache the result using post meta.
  add_post_meta($post_id, 'bible_' . $meta_hash, $output);

  // Return the rendered verses.
  return $output;
}

/**
 * Get the link for the verse.
 *
 * @param array $atts
 *  Shortcode attributes.
 * @param bool $show_version
 *  Whether or not show the Bible version where the verse was taken from.
 * @param string $anchor_text
 *  Anchor text to override the default one (=the scripture reference itself).
 * @return string
 *  The link.
 */
function biblembed_get_verse_link($atts, $show_version = TRUE, $anchor_text = NULL) {
  // If the anchor text for the link has not been specified (default)
  if (!$anchor_text) {
    // Create anchor text using the verse and, optionally (TRUE by default), the version of the translation.
    $anchor_text = $show_version ? $atts['verse'] . " (" . $atts['version'] . ")" : $atts['verse'];
  }

  // Return the link.
  return sprintf('<a href="%s">%s</a>',
    sprintf("http://www.biblegateway.com/passage/?search=%s&version=%s", urlencode($atts['verse']), $atts['version']),
    $anchor_text);
}

/**
 * Filter to transform a plain-text verse into a link to BibleGateway.
 *
 * @param string $content
 *  The content to filter.
 * @return string
 *  The filtered content.
 */
function biblembed_verse_to_link($content) {
  $regex = "/(\d{0,1}\s*)(\p{Lu}\w+)\s(\d{1,3}(?::\s?\d{1,3})?(?:\s?(?:[-&,]\d{1,3}:?\d{0,3}))*)(\s{0,1}\w*)/m";
  $content = preg_replace_callback(
    $regex,
    function ($matches) {
      if ((int)($matches[1]) < 1) $matches[1] = '';
      $search = $matches[1] . $matches[2] . $matches[3];
      $version = strlen($matches[4]) > 1 ? $matches[4] : biblembed_default_bibles(get_locale());
      $replacement = "<a href='http://www.biblegateway.com/passage/?search=%s&version=%s'>%s</a>";
      return sprintf($replacement, urlencode($search), urlencode($version), $matches[0]);
    },
    $content);
  return $content;
}

/* Add the shortcode handler. */
add_shortcode('bible', 'biblembed_shortcode_handler');

/* Add the filter on 'the_content'. */
add_filter('the_content', 'biblembed_verse_to_link');
add_filter('the_excerpt', 'biblembed_verse_to_link');
