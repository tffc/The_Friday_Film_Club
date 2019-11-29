<?php

namespace Drupal\tffc;

use Drupal\comment\Entity\Comment;

class Tffc {

  /**
   * Method used to calculate the current points
   *
   * @param $start_time - the start time code
   * @param int $total_points - the total score that can be won
   * @param int $end_points - the very last number that is classed as a point
   *
   * @return int
   */
  public static function calculatePoints($start_time, $total_points = 0, $end_points = 0) {
    // new output variable
    $output = $total_points;

    // get the timestamp from the time field
    $time = (int) $start_time;
    $now = time();

    // find the difference between the timestamp and now
    $diff = $now - $time;

    // output to the points value - diff
    $output = $output - $diff;


    // make sure the points cannot be after the end value
    if ($output <= $end_points) {
      $output = $end_points;
    }

    // make sure the score cannot go higher than the amount set
    if ($output >= $total_points) {
      $output = $total_points;
    }

    // return the value
    return $output;
  }


  /**
   * Load comments by node id and uid
   *
   * @param $nid
   * @param bool $uid
   *
   * @return array|int
   */
  public static function load_comments_by_nid($nid, $uid = FALSE) {
    $comments = [];

    $query = \Drupal::entityQuery('comment')
      ->condition('entity_id', $nid)
      ->condition('entity_type', 'node');

    // if we want to limit by uid
    if ($uid) {
      $query->condition('uid', $uid);
    }

    // run the query
    $cids = $query->sort('cid', 'DESC')->execute();

    // load the comment information
    if (!empty($cids)) {
      foreach ($cids as $cid) {
        $comments[] = Comment::load($cid);
      }
    }

    return $comments;
  }

  /**
   * Reply to a comment with correct or wrong information
   *
   * @param $entity_id
   * @param array $hints - contains the hint questions & the current hint number
   * @param bool $pid
   * @param bool $correct
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function create_comment_reply($entity_id, array $hints, $pid = FALSE, $correct = FALSE) {
    $reply = '';

    if ($correct) {
      $reply .= '<p class="comment-response comment-response-correct">' . self::random_correct_response() . '</p>';
    }
    else {
      $reply .= '<p class="comment-response comment-response-wrong">' . self::random_wrong_response() . '</p>';

      $hint = self::hint_response($hints['options'], $hints['count']);
      if ($hint) {
        $reply .= '<code class="comment-response-hint">' . $hint . '</code>';
      }
    }

    $values = [
      'entity_type' => 'node',
      'entity_id' => $entity_id,
      'field_name' => 'field_answers',
      'uid' => TFFC_REPLY_BOT_ID,
      'comment_type' => 'answers',
      'status' => 1,

      'subject' => $correct ? t('Correct answer') : t('Wrong answer'),
      'field_reply' => [
        'format' => 'full_html',
        'value' => $reply,
      ],
    ];

    // if we have a pid
    // lets add it here
    if ($pid) {
      $values['pid'] = $pid;
    }

    // This will create an actual comment entity out of our field values.
    $comment = Comment::create($values);

    // Last, we actually need to save the comment to the database.
    $comment->save();
  }

  /**
   * Returns a random wrong response
   *
   * @return mixed
   */
  private static function random_wrong_response() {
    $options = [
      t('Sorry that is not correct'),
      t('Nope, not quite right.'),
      t('Try again...'),
    ];

    $index = array_rand($options);

    return $options[$index];
  }

  /**
   * Returns a random correct response
   *
   * @return mixed
   */
  private static function random_correct_response() {
    $options = [
      t('Yes! Well done!!'),
      t('Correct, How did you know!?'),
      t('Exactly!'),
    ];

    $index = array_rand($options);

    return $options[$index];
  }

  /**
   * Returns the hint options
   *
   * @param $options
   * @param $count
   *
   * @return mixed
   */
  private static function hint_response($options, $count) {
    $num = count($options);

    // we have not got any more hints so lets return false
    if ($count > $num) {
      return FALSE;
    }

    // remove one for zero indexing
    $count = $count - 1;

    // check to make sure the option exists
    // if not return false
    if (!isset($options[$count]) && !isset($options[$count]['value'])) {
      return FALSE;
    }

    // return the found option
    return $options[$count]['value'];
  }

}