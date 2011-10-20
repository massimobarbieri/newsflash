<?php
# Copyright (C) 2009 Erich Steiger <me@erichsteiger.com>
# Copyright (C) 2011 Massimo Barbieri <massimo@fsfe.org> 
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
# http://www.gnu.org/copyleft/gpl.html

  if( !defined( 'MEDIAWIKI' ) )
    die( -1 );


define("NS_NEWS", 100);
$wgExtraNamespaces[NS_NEWS] = "News";
$wgContentNamespaces[] = NS_NEWS;


  $wgExtensionFunctions[] = "efNews";
  $wgHooks['OutputPageParserOutput'][] = 'newsParserOutput';

  function efNews() {
    global $wgParser;

    $wgParser->setHook( 'news', 'initNews' );
  }

  function initNews( $input, $args, $parser) {
    $newsParser = new News($input, $args, $parser);
    return $newsParser->parse();
  }

  class News {
    var $argumentArray;
    var $parser;

    function News($input, $args, $parser) {
      $this->parser = $parser;
      $this->args = $args;
      $this->parseArguments();
    }

    function parse() {
      #$output = "<h2>" . $this->argumentArray["title"] . "</h2>";
      $output .= $this->getNews($this->parser);
      return $output;
    }

    function parseArguments() {
      if ( isset( $this->args["title"] )) {
        $this->argumentArray["title"] = $this->args["title"];
      } else {
        $this->argumentArray["title"] = "News";
      }
      if ( isset( $this->args["category"] )) {
        $this->argumentArray["category"] = $this->args["category"];
      } else {
        $this->argumentArray["category"] = "News";
      }

      if ( isset( $this->args["rows"] ) && is_numeric( $this->args["rows"] ) ) {
        $this->argumentArray["rows"] = $this->args["rows"];
        $this->argumentArray["showPages"] = false;
      } else {
        $this->argumentArray["rows"] = "15";
        $this->argumentArray["showPages"] = true;
      }
      $this->argumentArray["page"] = $_GET["page"];
      if ($this->argumentArray["page"] == "") {
        $this->argumentArray["page"] = 1;
      }
    }

    function formatDate($date) {
      $y = substr($date,0,4);
      $m = substr($date,5,2);
      $d = substr($date,8,2);
      return $d . "." .  $m . "." . $y;
    }

    function getNews($parser) {
      $dbr = wfGetDB( DB_SLAVE );

      list( $page, $categorylinks, $revision, $text) = $dbr->tableNamesN( 'page', 'categorylinks', 'revision', 'text');
      $sql = "select * from (
                     SELECT page_id, page_namespace, page_title, 
                     (select old_text FROM $text JOIN $revision ON rev_text_id = old_id WHERE rev_id = (select max(rev_id) from $revision WHERE rev_page = page_id)) as old_text,
                     (select rev_timestamp FROM $revision WHERE rev_id = (select min(rev_id) from $revision WHERE rev_page = page_id)) as rev_timestamp,
                     STR_TO_DATE(substr((select rev_timestamp FROM $revision WHERE rev_id = (select min(rev_id) from $revision WHERE rev_page = page_id)),1,8), '%Y%m%d') as articleDate
                FROM $page 
                JOIN $categorylinks ON page_id = cl_from 
               WHERE cl_to =" . $dbr->addQuotes($this->argumentArray["category"]) . ") V
               ORDER BY REV_timestamp desc
               LIMIT " .  $this->argumentArray["rows"] * ($this->argumentArray["page"]-1) . "," . $this->argumentArray["rows"];

      $res = $dbr->query( $sql, 'news' );
      if ($dbr->numRows($res) <= 0) {
        return "";
      }
	$output .= "<ul>";
      while ( $row = $dbr->fetchObject( $res ) ) {
		$contentDivId = "news" . $row->page_id;
		$output .= "<li><a href=\"/index.php/" . Title::makeTitle( $row->page_namespace, $row->page_title) . "\">" . $this->formatDate($row->articleDate) . ": " . Title::makeTitle( 0, $row->page_title) . "</a></li>";
      }
	$output .= "</ul>";
      $dbr->freeResult($res);

      $res = $dbr->query( "SELECT count(*) as pageCount FROM $page 
                JOIN $categorylinks ON page_id = cl_from WHERE cl_to =" . $dbr->addQuotes($this->argumentArray["category"]), 'newsCount' );
      $row = $dbr->fetchObject( $res );

#TODO: Controllare questa parte
      if ($row->pageCount > $this->argumentArray["rows"]) {
        if ($this->argumentArray["showPages"] == true) {
          $pages = "";

          for ($i = 0; $i * $this->argumentArray["rows"] < $row->pageCount; $i++) {
            if ($pages != "") {
              $pages .= " ";
            }
            $pages .= "<a href=\"?page=" . ($i + 1) . "\">" . ($i + 1) . "</a>";
          }

        }
      }
      return $output;
    }
  }

  /**
   * Hook callback that injects messages and things into the <head> tag
   * Does nothing if $parserOutput->mSmoothGalleryTag is not set
   */
  function newsParserOutput( &$outputPage, &$parserOutput )  {

    $outputPage->addScript('<script  type="text/javascript" src="/extensions/News/scripts/main.js"></script>');
    return true;
  }

/**
 * Add extension information to Special:Version
 */
$wgExtensionCredits['other'][] = array(
	'name'        => 'Newsflash extension',
	'version'     => '0.0.1',
	'author'      => 'Erich Steiger changed by Massimo Barbieri',
	'description' => 'Allows users to create News-Pages and News-Lists limited to n elements. News are articles of a specific category. Default is Category News, but can be overruled by attribute category',
	'descriptionmsg' => 'news-desc',
	'url'         => 'http://lug.42019.it',
);

?>
