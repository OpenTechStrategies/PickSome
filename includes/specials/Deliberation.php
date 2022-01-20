<?php

class Deliberation extends SpecialPage {
  public const ADD = 0;
  public const REMOVE = 1;

  public function __construct() {
    parent::__construct( 'Deliberation' );
  }

  public function execute( $subPage ) {
    if(!$this->getUser()->isAllowed('deliberation')) {
      throw new PermissionsError('deliberation-all');
    }

    switch ($this->getRequest()->getVal('cmd')) {
      case 'pick':
        $page_picked = WikiPage::newFromID($this->getRequest()->getVal('page'))->getTitle();
        if(!$this->getUser()->isAllowed('deliberation-write')) {
          throw new PermissionsError('deliberation-all');
        }
        $this->addPickToDb();
        $this->
          getOutput()->
          redirect(
            $page_picked->getFullURL()
          );
        $this->log("pick", $page_picked);
        return;
      case 'start':
        DeliberationSession::enable();
        $this->
          getOutput()->
          redirect(
            Title::newFromText($this->getRequest()->getVal('returnto'))->getFullURL()
          );
        return;
      case 'stop':
        DeliberationSession::disable();
        $this->
          getOutput()->
          redirect(
            Title::newFromText($this->getRequest()->getVal('returnto'))->getFullURL()
          );
        return;
      case 'adminremove':
        if(!$this->getUser()->isAllowed('deliberation-admin')) {
          throw new PermissionsError('deliberation-all');
        }
        $this->adminremovePickFromDb();
        $this->
          getOutput()->
          redirect(
            SpecialPage::getTitleFor('Deliberation')->getLocalUrl()
          );
        return;
      case 'remove':
        if(!$this->getUser()->isAllowed('deliberation-write')) {
          throw new PermissionsError('deliberation-all');
        }
        $this->removePickFromDb();
        $this->
          getOutput()->
          redirect(
            WikiPage::newFromID($this->getRequest()->getVal('returnto'))->getTitle()->getFullURL()
          );
        $page_removed = WikiPage::newFromID($this->getRequest()->getVal('page'))->getTitle();
        $this->log("remove", $page_removed);
        return;
    }
    $this->renderDeliberationPage();
  }

  public static function canAdd($title) {
    return Deliberation::can($title, Deliberation::ADD);
  }

  public static function canRemove($title) {
    return Deliberation::can($title, Deliberation::REMOVE);
  }

  private static function can($title, $permission) {
    global $wgDeliberationPage;

    if ($wgDeliberationPage) {
      if(is_string($wgDeliberationPage) && !preg_match($wgDeliberationPage, $title->getPrefixedText())) {
        return false;
      } else if(is_callable($wgDeliberationPage) && !call_user_func($wgDeliberationPage, $title, $permission)) {
        return false;
      }
      // If it's not a string, and it's not callable, we'll default to true
    }

    return true;
  }

  private function renderDeliberationPage() {
    $out = $this->getOutput();

    $this->setHeaders();
    $out->setPageTitle(wfMessage("deliberation-global-list"));
    $template = new DeliberationGlobalTemplate();
    $template->set('users_picked_pages', $this->usersPickedPages());
    $template->set('picked_pages', $this->allPickedPages());
    $out->addTemplate($template);
  }

  private function addPickToDb() {
    $dbw = wfGetDB(DB_MASTER);
    $page = $this->getRequest()->getVal('page');
    $user_id = $this->getUser()->getId();
    if(!$this->alreadyPickedPage($page, $user_id, $dbw) &&
      !$this->alreadyPickedTwoPages($user_id, $dbw)) {

      $dbw->insert(
        "Deliberation",
        [
          "page_id" => $page,
          "user_id" => $user_id
        ]);
    }
  }

  private function alreadyPickedPage($page, $user_id, $dbw) {
    return
      ($dbw->numRows(
        $dbw->select(
          "Deliberation",
          ["page_id"],
          [
            "page_id" => $this->getRequest()->getVal('page'),
            "user_id" => $this->getUser()->getId()
          ]))
      > 0);
  }

  private function alreadyPickedTwoPages($user_id, $dbw) {
    global $wgDeliberationNumberOfPicks;
    return (count($this->usersPickedPages()) >= $wgDeliberationNumberOfPicks);
  }

  private function removePickFromDb() {
    $dbw = wfGetDB(DB_MASTER);
    $dbw->delete(
      "Deliberation",
      [
        "page_id" => $this->getRequest()->getVal('page'),
        "user_id" => $this->getUser()->getId()
      ]);
  }

  private function adminremovePickFromDb() {
    $dbw = wfGetDB(DB_MASTER);
    $dbw->delete(
      "Deliberation",
      [
        "page_id" => $this->getRequest()->getVal('page'),
        "user_id" => $this->getRequest()->getVal('user')
      ]);
  }

  private function allPickedPages() {
    global $wgDeliberationSortFunction;

    $dbw = wfGetDB(DB_MASTER);
    $res = $dbw->select("Deliberation", ["page_id", "user_id"]);
    $picked_pages = [];
    foreach($res as $row) {
      $page_id = $row->page_id;
      if(!array_key_exists($page_id, $picked_pages)) {
        $picked_pages[$page_id] = [WikiPage::newFromID($page_id), []];
      }

      array_push($picked_pages[$page_id][1], User::newFromID($row->user_id));
    }

    if($wgDeliberationSortFunction) {
      $cmp_page = function($p1, $p2) {
        global $wgDeliberationSortFunction;
        return call_user_func($wgDeliberationSortFunction, $p1[0]->getTitle(), $p2[0]->getTitle());
      };
      usort($picked_pages, $cmp_page);
    }

    return $picked_pages;
  }

  private function usersPickedPages() {
    $dbw = wfGetDB(DB_MASTER);
    $res = $dbw->select(
      "Deliberation",
      ["page_id"],
      ["user_id" => $this->getUser()->getId()]);
    $picked_pages = [];
    foreach($res as $row) {
      array_push($picked_pages, WikiPage::newFromID($row->page_id));
    }
    return $picked_pages;
  }

  private function log($action, $page) {
    $log = new LogPage('deliberation', false);

    $log->addEntry($action, $page, $page->getText());
  }
}
