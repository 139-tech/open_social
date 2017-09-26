<?php

namespace Drupal\social_demo;

use Drupal\book\BookManager;
use Drupal\user\UserStorageInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\NodeInterface;
use Drupal\group\Entity\GroupContent;
use Drush\Log\LogLevel;

/**
 * Class DemoNode.
 *
 * @package Drupal\social_demo
 */
abstract class DemoNode extends DemoContent {

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\entity\EntityStorageInterface
   */
  protected $groupStorage;

  /**
   * The book manager.
   *
   * @var \Drupal\book\BookManager
   */
  protected $bookManager;

  /**
   * DemoNode constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, DemoContentParserInterface $parser, UserStorageInterface $user_storage, EntityStorageInterface $group_storage, BookManager $book_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->parser = $parser;
    $this->groupStorage = $group_storage;
    $this->userStorage = $user_storage;
    $this->bookManager = $book_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('social_demo.yaml_parser'),
      $container->get('entity.manager')->getStorage('user'),
      $container->get('entity.manager')->getStorage('group'),
      $container->get('book.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function createContent() {
    $data = $this->fetchData();

    foreach ($data as $uuid => $item) {
      // Must have uuid and same key value.
      if ($uuid !== $item['uuid']) {
        drush_log(dt("Node with uuid: {$uuid} has a different uuid in content."), LogLevel::ERROR);
        continue;
      }

      // Check whether node with same uuid already exists.
      $nodes = $this->entityStorage->loadByProperties([
        'uuid' => $uuid,
      ]);

      if (reset($nodes)) {
        drush_log(dt("Node with uuid: {$uuid} already exists."), LogLevel::WARNING);
        continue;
      }

      // Try to load a user account (author's account).
      $account = $this->loadByUuid('user', $item['uid']);

      if (!$account) {
        drush_log(dt("Account with uuid: {$item['uid']} doesn't exists."), LogLevel::ERROR);
        continue;
      }

      // Create array with data of a node.
      $item['uid'] = $account->id();

      if (isset($item['created'])) {
        $item['created'] = $this->createDate($item['created']);
      }
      else {
        $item['created'] = REQUEST_TIME;
      }

      $entry = $this->getEntry($item);
      $entity = $this->entityStorage->create($entry);
      $entity->save();

      if ($entity->id()) {
        $this->content[$entity->id()] = $entity;

        if (!empty($item['group'])) {
          $this->createGroupContent($entity, $item['group']);
        }

        if (!empty($item['book'])) {
          $this->createBookLink($entity, $item['book']);
        }
      }
    }

    return $this->content;
  }

  /**
   * Converts a date in the correct format.
   *
   * @param string $date_string
   *    The date.
   *
   * @return int|false
   *    Returns a timestamp on success, false otherwise.
   */
  protected function createDate($date_string) {
    if ($date_string === 'now') {
      return time();
    }
    // Split from delimiter.
    $timestamp = explode('|', $date_string);

    $date = strtotime($timestamp[0]);
    $date = date('Y-m-d', $date) . 'T' . $timestamp[1] . ':00';

    return strtotime($date);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntry(array $item) {
    $entry = [
      'uuid' => $item['uuid'],
      'langcode' => $item['langcode'],
      'created' => $item['created'],
      'uid' => $item['uid'],
      'title' => $item['title'],
      'type' => $item['type'],
      'body' => [
        'value' => $this->checkMentionOrLinkByUuid($item['body']),
        'format' => 'basic_html',
      ],
    ];

    return $entry;
  }

  /**
   * Creates a group content.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   Object of a node.
   * @param string $uuid
   *   UUID of a group.
   */
  public function createGroupContent(NodeInterface $entity, $uuid) {
    // Load the group.
    $groups = $this->groupStorage->loadByProperties([
      'uuid' => $uuid,
    ]);

    if ($groups) {
      $group = current($groups);
      // Get the group content plugin.
      $plugin_id = 'group_node:' . $entity->bundle();
      $plugin = $group->getGroupType()->getContentPlugin($plugin_id);
      $group_content = GroupContent::create([
        'type' => $plugin->getContentTypeConfigId(),
        'gid' => $group->id(),
        'entity_id' => $entity->id(),
      ]);
      $group_content->save();
    }
  }

  public function createBookLink(NodeInterface $entity, $book) {
    // Load book by book ID. Set saveBookLink false if not a new book.

    $bid = 0;
    if ($entity->uuid() == $book['id']) {
      $bid = $entity->id();
    }
    else {
      $book_entity = $this->loadByUuid('node', $book['id']);
      $bid = $book_entity->id();
    }

    $pid = $bid;
    if (!empty($book['parent'])) {
      $book_parent = $this->loadByUuid('node', $book['parent']);
      $pid = $book_parent->id();
    }

    $weight = 0;
    if (!empty($book['weight'])) {
      $weight = $book['weight'];
    }

    if ($entity->uuid() == $book['id']) {
      $pid = 0;
    }

    $link = [
      'nid' => $entity->id(),
      'bid' => $bid,
      'pid' => $pid,
      'weight' => $weight,
    ];

    $this->bookManager->saveBookLink($link, TRUE);
  }

}
