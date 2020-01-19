<?php

namespace Drupal\tffc_importer\Services;


use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\tffc_importer\TffcImporterMediaTrait;

/**
 * Class TffcSync
 *
 * @package Drupal\TffcSync
 */
class TffcSync {

  use TffcImporterMediaTrait;

  /**
   * The node id that is being synced
   *
   * @var int
   */
  protected $nid;

  /**
   * The node that is being synced
   *
   * @var
   */
  protected $node;

  /**
   * Holds errors
   *
   * @var array
   */
  protected $errors = [];


  /**
   * Holds success
   *
   * @var array
   */
  protected $success = [];

  /**
   * The node has changed and can be saved
   *
   * @var bool
   */
  protected $changed = FALSE;

  /**
   * @var int
   */
  protected $hintsToFind = 3;

  /**
   * Gets all films in the system
   *
   * @return array|int
   */
  public function getAllFilms() {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'film');

    return $query->execute();
  }

  /**
   * Gets all films that are syncable
   *
   * @return array|int
   */
  public function getSyncableFilms() {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'film')
      ->condition('field_validated', FALSE)
      ->condition('field_complete', FALSE);

    return $query->execute();
  }

  /**
   * Sync information for the selected node
   *
   * @param int $nid
   *
   */
  public function syncInformation(int $nid) {

    try {
      $this->loadNode($nid);
    } catch (\Exception $e) {
      $this->setError($e->getMessage());
      return;
    }

    $this->getObscuredImage();

    try {
      $this->generateHints();
    } catch (\Exception $e) {
      $this->setError($e->getMessage());
      return;
    }

    $this->saveNode();

  }

  /**
   * Gets the obscured Image
   */
  protected function getObscuredImage() {

  }


  /**
   * Generates hints
   *
   * @throws \Exception
   */
  protected function generateHints() {
    $hints = [];
    // get the release date
    $releaseDate = $this->node->field_release_date->value;
    if ($this->isValidDate($releaseDate)) {
      $year = date('Y', strtotime($releaseDate));
      $hints[] = sprintf('Released in %s.', $year);
    }
    else {
      $this->setError(t('Hints - Could not find valid release date.'));
    }

    // get the directors
    $directors = $this->node->field_imdb_director->getValue();
    if (is_array($directors) && !empty($directors)) {
      $key = array_rand($directors);
      $director = $directors[$key]['value'];
      $hints[] = sprintf('Was directed by %s.', $director);
    }
    else {
      $this->setError(t('Hints - Could not find valid release date.'));
    }

    // gets the genre information
    $genres = $this->node->field_imdb_genre->getValue();
    if (is_array($genres) && !empty($genres)) {
      $key = array_rand($genres);
      $genre = $this->loadTaxonomyValue($genres[$key]['target_id']);
      $hints[] = sprintf('It is a %s film.', $genre);
    }
    else {
      $this->setError(t('Hints - Could not find valid genre.'));
    }

    $actors = $this->node->field_imdb_actors->getValue();
    if (is_array($genres) && !empty($genres)) {
      $key = array_rand($actors);
      $actor = $actors[$key]['value'];
      $hints[] = sprintf('Starting %s.', $actor);
    }
    else {
      $this->setError(t('Hints - Could not find valid actors.'));
    }

    $hints = $this->selectXRandomItems($hints, $this->hintsToFind);
    if (count($hints) === $this->hintsToFind) {
      $this->node->set('field_hints', $hints);
      $this->changed = TRUE;
    }
    else {
      $this->setError(t('Hints - Count not find the correct amount of hints.'));
    }
  }

  /**
   * @return array
   */
  public function getErrors(): array {
    return $this->errors;
  }

  /**
   * Set error
   *
   * @param $error
   */
  protected function setError($error) {
    $this->errors[] = t('nid: @nid', ['@nid' => $this->nid]) . " - " . $error;
  }

  /**
   * Marks a node as complete with information
   */
  protected function markAllInformationAsComplete() {

  }

  /**
   * load a node
   *
   * @param $nid
   *
   * @throws \Exception
   */
  protected function loadNode($nid) {
    $this->nid = $nid;
    $this->node = Node::load($nid);

    if (!$this->node) {
      throw new \Exception(t('Could not load node.'));
    }

    $type = $this->node->getType();
    if ($type !== "film") {
      throw new \Exception(t('The node loaded is not a type of film.'));
    }

    $is_invalid = $this->node->field_invalid->value;
    if ($is_invalid) {
      throw new \Exception(t('This film has been marked as invalid.'));
    }

    $is_valid = $this->node->field_validated->value;
    if ($is_valid) {
      throw new \Exception(t('This film has been marked as valid.'));
    }

    $is_valid = $this->node->field_complete->value;
    if ($is_valid) {
      throw new \Exception(t('This film has been completed as valid.'));
    }
  }

  /**
   * Save node
   */
  protected function saveNode() {
    if ($this->changed) {
      $this->node->save();
    }
  }

  /**
   * @param $date
   *
   * @return bool
   */
  private function isValidDate($date) {
    $tempDate = explode('-', $date);
    return checkdate($tempDate[1], $tempDate[2], $tempDate[0]);
  }

  /**
   * @param $tid
   *
   * @return mixed
   */
  private function loadTaxonomyValue($tid) {
    $taxonomy = Term::load($tid);
    return $taxonomy->getName();
  }

  /**
   * @param array $array
   * @param int $count
   *
   * @return array
   * @throws \Exception
   */
  private function selectXRandomItems(array $array, int $count) {
    $output = [];
    $items = $array;
    $count_array = count($items);

    // cannot pull out more items than in array
    if ($count > $count_array) {
      throw new \Exception(t('Looking for more items then given in the array.'));
    }

    // cannot pull out less than zero items
    if ($count <= 0) {
      throw new \Exception(t('Cannot pull 0 or less random items.'));
    }

    // add the random item to the array and unset it
    for ($i = 0; $i < $count; $i++) {
      $key = array_rand($array);
      $item = $array[$key];
      unset($array[$key]);
      $output[] = $item;
    }

    return $output;
  }

}
