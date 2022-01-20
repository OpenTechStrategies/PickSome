<?php
class DeliberationHooks {

  public static function onSidebarBeforeOutput(Skin $skin, &$bar) {
    global $wgDeliberationPage;

    if (!$skin->getUser()->isLoggedIn()) {
      return true;
    }

    if (!$skin->getUser()->isAllowed("deliberation")) {
      return true;
    }

    $page_url = '';
    if (!is_null($skin->getTitle()) && $skin->getTitle()->isKnown()) {
      $page_url = $skin->getTitle()->getFullText();
    }

    $deliberation_links = [];
    $title = $skin->getTitle();

    if ($skin->getUser()->isAllowed("deliberation-write")) {
      if(DeliberationSession::isEnabled()) {
        $deliberation_links[] = [
          "msg" => "deliberation-stop",
          "href" => SpecialPage::getTitleFor('Deliberation')->getLocalUrl(
            ['cmd' => 'stop', 'returnto' => $page_url]
          )
        ];
      } else {
        $deliberation_links[] = [
          "msg" => "deliberation-start",
          "href" => SpecialPage::getTitleFor('Deliberation')->getLocalUrl(
            ['cmd' => 'start', 'returnto' => $page_url]
          )
        ];
      }
    }

    $deliberation_links[] = [
      "msg" => "deliberation-all",
      "href" => SpecialPage::getTitleFor('Deliberation')->getLocalUrl()
    ];

    $bar['deliberation-title'] = $deliberation_links;

    return true;
  }

  public static function siteNoticeAfter( &$siteNotice, $skin ) {
    global $wgDeliberationPage;

    if(!DeliberationSession::isEnabled()) {
      return true;
    }

    if (!($skin->getUser()->isAllowed("deliberation") && $skin->getUser()->isAllowed("deliberation-write"))) {
      return true;
    }

    $title = $skin->getTitle();

    if (!$title->exists()) {
      return true;
    }

    if (!$skin->getUser()->isLoggedIn()) {
      return true;
    }

    $dbw = wfGetDB(DB_MASTER);
    $res = $dbw->select(
      'Deliberation',
      ['page_id'],
      'user_id = ' . $skin->getUser()->getId()
    );

    $can_add = Deliberation::canAdd($title);
    $can_remove = Deliberation::canRemove($title);

    if(!($can_add || $can_remove)) {
      return true;
    }

    $page_id = $skin->getWikiPage()->getId();
    $selected_pages = [];
    foreach($res as $row) {
      $selected_pages[$row->page_id] = WikiPage::newFromID($row->page_id);
    }

    $siteNotice .= self::renderDeliberationBox($title, $selected_pages, $page_id, $can_add, $can_remove);
    return true;
  }

  # Rendering via string concatenation is not ideal, but how to
  # delegate to the mediawiki templating system deserves more
  # discussion.
  public static function renderDeliberationBox($title, $selected_pages, $page_id, $can_add, $can_remove) {
    global $wgDeliberationNumberOfPicks;
    $html = "";
    $html .= "<div style='border:1px solid black;padding:10px;text-align:left;margin-top:10px;background-color:#F2F2F2'>";
    $html .= "<h2 style='margin-top:0px;border-bottom:0px'>";
    $html .= "<span style='text-decoration:underline'>" . wfMessage("deliberation-choices") . "</span>";
    $html .= "<span style='font-size:80%'> (<a href='";
    $html .= SpecialPage::getTitleFor('Deliberation')->getLocalUrl(
      ['cmd' => 'stop',  'returnto' => $title->getFullText()]
    );
    $html .= "'>" . wfMessage("deliberation-close-window") . "</a>)</span>";
    $html .= "</h2>";

    $page_already_selected = false;

    $html .= "<ul>";
    if(count($selected_pages) > 0) {
      $html .= "<li>" . wfMessage("deliberation-my-picks") . " (" . count($selected_pages) . "/" . $wgDeliberationNumberOfPicks . ")";
      $html .= "<ul>";
      if(count($selected_pages) >= $wgDeliberationNumberOfPicks && !array_key_exists($page_id, $selected_pages)) {
        $html .= "<li style='font-style:italic'>" . wfMessage("deliberation-remove-below");
      }
      foreach($selected_pages as $selected_page_id => $selected_page) {
        $html .= "<li>";
        if($page_id == $selected_page_id) {
          $html .= "<span style='font-style:italic'>(" . wfMessage("deliberation-current") . ")</span> ";
        } else {
          $html .= "<a href='" . $selected_page->getTitle()->getLocalUrl() . "'>";
        }
        $html .= $selected_page->getTitle()->getPrefixedText();
        if($page_id != $selected_page_id) {
          $html .= "</a>";
        }
        if($can_remove) {
          $html .= " (<a href='";
          $html .= SpecialPage::getTitleFor('Deliberation')->getLocalUrl(
            ['cmd' => 'remove', 'page' => $selected_page_id, 'returnto' => $page_id]
          );
          $html .= "'>" . wfMessage("deliberation-unpick") . "</a>)";
          $html .= "\n";
        }
      }
      $html .= "</ul>";
    }
    if (!(array_key_exists($page_id, $selected_pages)) && !(count($selected_pages) >= $wgDeliberationNumberOfPicks) && $can_add) {
      $html .= "<li><a rel='nofollow' href='";
      $html .= SpecialPage::getTitleFor('Deliberation')->getLocalUrl(
        ['cmd' => 'pick', 'page' => $page_id]
      );
      $html .= "'>" . wfMessage("deliberation-pick") . "</a>";
      $html .= " [" . $title->getPrefixedText() . "]";
    }
    $html .= "<li><a href='";
    $html .= SpecialPage::getTitleFor('Deliberation')->getLocalUrl();
    $html .= "'>" . wfMessage("deliberation-view-all") . "</a>";
    $html .= "</ul>";

    $html .= "</div>";

    return $html;
  }

  public static function onLoadExtensionSchemaUpdates( $updater ) {
    $updater->addExtensionTable("Deliberation", __DIR__ . "/../sql/deliberation.sql");
  }
}
?>
