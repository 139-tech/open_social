<?php

namespace Drupal\group_core_comments\Plugin\Field\FieldFormatter;

use Drupal\comment\Plugin\Field\FieldFormatter\CommentDefaultFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupContent;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Link;

/**
 * Plugin implementation of the 'comment_group_content' formatter.
 *
 * @FieldFormatter(
 *   id = "comment_group_content",
 *   label = @Translation("Comment on group content"),
 *   field_types = {
 *     "comment"
 *   }
 * )
 */
class CommentGroupContentFormatter extends CommentDefaultFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $output = parent::viewElements($items, $langcode);
    $entity = $items->getEntity();

    // Exclude entities without the set id.
    if (!empty($entity->id())) {
      $group_contents = GroupContent::loadByEntity($entity);
    }

    if (!empty($group_contents)) {
      // Add cache contexts.
      $output['#cache']['contexts'][] = 'group.type';
      $output['#cache']['contexts'][] = 'group_membership';

      $account = \Drupal::currentUser();
      /** @var \Drupal\group\Entity\GroupInterface $group */
      $group = reset($group_contents)->getGroup();
      $group_url = $group->toUrl('canonical', ['language' => $group->language()]);

      $access_post_comments = $this->getPermissionInGroups('post comments', $account, $group_contents, $output);
      if ($access_post_comments->isForbidden()) {
        if ($group->hasPermission('join group', $account)) {
          $description = $this->t('You are not allowed to comment on content in a group you are not member of. You can join the group @group_link.',
            [
              '@group_link' => Link::fromTextAndUrl($this->t('here'), Url::fromUserInput('#'))
                ->toString(),
            ]
          );

          $group_image = NULL;
          if ($group->hasField('field_group_image') && !$group->get('field_group_image')->isEmpty()) {
            /** @var \Drupal\file\FileInterface $image_file */
            $image_file = $group->get('field_group_image')->entity;
            $group_image = [
              '#theme' => 'image_style',
              '#style_name' => 'social_xx_large',
              '#uri' => $image_file->getFileUri(),
            ];
          }

          $output[0]['comment_form'] = [
            '#theme' => 'forbidden_post_comments',
            '#description' => $description,
            '#group_info' => [
              'image' => $group_image,
              'label' => $group->label(),
              'type' => $group->getGroupType()->label(),
              'members_count' => count($group->getMembers()),
              'url' => $group_url->toString(),
            ],
            '#action' => [
              'label' => $this->t('Join group'),
              'url' => Url::fromRoute('group_core_comments.quick_join_group', ['group' => $group->id()]),
            ],
          ];
        }
        else {
          $description = $this->t('You are not allowed to comment on content in a group you are not member of. You can join the group @group_link.',
            [
              '@group_link' => Link::fromTextAndUrl($this->t('here'), $group_url)
                ->toString(),
            ]
          );

          $output[0]['comment_form'] = [
            '#prefix' => '<hr>',
            '#markup' => $description,
          ];
        }
      }

      $access_view_comments = $this->getPermissionInGroups('access comments', $account, $group_contents, $output);
      if ($access_view_comments->isForbidden()) {
        $description = $this->t('You are not allowed to view comments on content in a group you are not member of. You can join the group @group_link.',
          [
            '@group_link' => Link::fromTextAndUrl($this->t('here'), $group_url)
              ->toString(),
          ]
        );
        unset($output[0]);
        $output[0]['comments'] = [
          '#markup' => $description,
        ];
      }

    }
    return $output;
  }

  /**
   * Checks if account was granted permission in group.
   */
  protected function getPermissionInGroups($perm, AccountInterface $account, $group_contents, &$output) {
    $renderer = \Drupal::service('renderer');

    foreach ($group_contents as $group_content) {
      $group = $group_content->getGroup();

      // Add cacheable dependency.
      $membership = $group->getMember($account);
      $renderer->addCacheableDependency($output, $membership);

      if ($group->hasPermission($perm, $account)) {
        return AccessResult::allowed()->cachePerUser();
      }
    }
    // Fallback.
    return AccessResult::forbidden()->cachePerUser();
  }

}
