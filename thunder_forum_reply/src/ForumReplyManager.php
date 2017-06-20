<?php

namespace Drupal\thunder_forum_reply;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Forum reply manager contains common functions to manage forum reply fields.
 */
class ForumReplyManager implements ForumReplyManagerInterface {

  use StringTranslationTrait;

  /**
   * The current database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity query factory.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $queryFactory;

  /**
   * Construct the ForumReplyManager object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The current database connection.
   * @param \Drupal\Core\Entity\Query\QueryFactory $query_factory
   *   The entity query factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(EntityManagerInterface $entity_manager, Connection $connection, QueryFactory $query_factory, ModuleHandlerInterface $module_handler, AccountInterface $current_user) {
    $this->connection = $connection;
    $this->currentUser = $current_user;
    $this->entityManager = $entity_manager;
    $this->moduleHandler = $module_handler;
    $this->queryFactory = $query_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getFields() {
    $map = $this->entityManager->getFieldMapByFieldType('thunder_forum_reply');

    return isset($map['node']) ? $map['node'] : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCountNewReplies(NodeInterface $node, $field_name = NULL, $timestamp = 0) {
    // Only perform this check for authenticated users.
    if ($this->currentUser->isAuthenticated()) {
      // Check forum reply history for number of unread replies (if module is
      // enabled) while ignoring optional passed in timestamp.
      if ($this->moduleHandler->moduleExists('thunder_forum_reply_history')) {
        $query = $this->connection->select('thunder_forum_reply_field_data', 'fr');
        $query->leftJoin('thunder_forum_reply_history', 'h', 'fr.frid = h.frid AND h.uid = :uid', [':uid' => $this->currentUser->id()]);
        $query->addExpression('COUNT(fr.frid)', 'count');
        $query->addTag('entity_access')
          ->addTag('thunder_forum_reply_access')
          ->addMetaData('base_table', 'thunder_forum_reply')
          ->addMetaData('entity', $node);

        if ($field_name) {
          // Limit to a particular field.
          $query->condition('fr.field_name', $field_name);
          $query->addMetaData('field_name', $field_name);
        }

        $count = $query
          ->condition('fr.nid', $node->id())
          ->condition('fr.created', HISTORY_READ_LIMIT, '>')
          ->isNull('h.frid')
          ->execute()
          ->fetchField();

        return $count > 0;
      }

      // @todo Replace module handler with optional history service injection
      //   after https://www.drupal.org/node/2081585.
      elseif ($this->moduleHandler->moduleExists('history')) {
        // Retrieve the timestamp at which the current user last viewed this
        // forum node.
        if (!$timestamp) {
          $timestamp = history_read($node->id());
        }

        $timestamp = ($timestamp > HISTORY_READ_LIMIT ? $timestamp : HISTORY_READ_LIMIT);

        // Use the timestamp to retrieve the number of new forum replies.
        $query = $this->queryFactory->get('thunder_forum_reply')
          ->condition('nid', $node->id())
          ->condition('created', $timestamp, '>')
          ->condition('status', ForumReplyInterface::PUBLISHED);

        if ($field_name) {
          // Limit to a particular field.
          $query->condition('field_name', $field_name);
        }

        return $query->count()->execute();
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isUnreadReply(ForumReplyInterface $reply, AccountInterface $account) {
    if ($this->moduleHandler->moduleExists('thunder_forum_reply_history') && $this->currentUser->isAuthenticated()) {
      $query = $this->connection->select('thunder_forum_reply_field_data', 'fr');
      $query->leftJoin('thunder_forum_reply_history', 'h', 'fr.frid = h.frid AND h.uid = :uid', [':uid' => $account->id()]);
      $query->addExpression('COUNT(fr.frid)', 'count');

      return $query
        ->condition('fr.frid', $reply->id())
        ->condition('fr.created', HISTORY_READ_LIMIT, '>')
        ->isNull('h.frid')
        ->execute()
        ->fetchField() > 0;
    }

    return FALSE;
  }

}
