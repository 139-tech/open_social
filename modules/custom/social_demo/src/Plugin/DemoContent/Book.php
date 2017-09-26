<?php

namespace Drupal\social_demo\Plugin\DemoContent;

use Drupal\book\BookManager;
use Drupal\node\Entity\Node;
use Drupal\social_demo\DemoNode;
use Drupal\social_demo\DemoContentParserInterface;
use Drupal\user\UserStorageInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\file\FileStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Book Plugin for demo content.
 *
 * @DemoContent(
 *   id = "book",
 *   label = @Translation("Book page"),
 *   source = "content/entity/book.yml",
 *   entity_type = "node"
 * )
 */
class Book extends DemoNode {

  /**
   * The file storage.
   *
   * @var \Drupal\file\FileStorageInterface
   */
  protected $fileStorage;

  /**
   * The book manager.
   *
   * @var \Drupal\book\BookManager
   */
  protected $bookManager;

  /**
   * Page constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, DemoContentParserInterface $parser, UserStorageInterface $user_storage, EntityStorageInterface $group_storage, FileStorageInterface $file_storage, BookManager $book_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $parser, $user_storage, $group_storage, $book_manager);

    $this->fileStorage = $file_storage;
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
      $container->get('entity.manager')->getStorage('file'),
      $container->get('book.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntry(array $item) {
    $entry = parent::getEntry($item);
    $entry['field_content_visibility'] = $item['field_content_visibility'];

    // Load image by uuid and set to node.
    if (!empty($item['field_book_image'])) {
      $entry['field_book_image'] = $this->prepareImage($item['field_book_image']);
    }

    // Load attachments to node.
    if (!empty($item['field_files'])) {
      $entry['field_files'] = $this->prepareAttachment($item['field_files']);
    }

    if (!empty($item['alias'])) {
      $entry['path'] = [
        'alias' => $item['alias'],
      ];
    }

    return $entry;
  }

  /**
   * Prepares data about an image of node.
   *
   * @param string $uuid
   *    The uuid for the image.
   *
   * @return array|null
   *    Returns an array or null.
   */
  protected function prepareImage($uuid) {
    $value = NULL;
    $files = $this->fileStorage->loadByProperties([
      'uuid' => $uuid,
    ]);

    if ($files) {
      $value = [
        [
          'target_id' => current($files)->id(),
        ],
      ];
    }

    return $value;
  }

  /**
   * The function for creating a book from the demo content.
   *
   * @param \Drupal\node\Entity\Node $entity
   *    The related entity.
   * @param $book
   *    The book.
   */
  public function createBookLink(Node $entity, $book) {
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
