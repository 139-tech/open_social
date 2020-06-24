<?php

namespace Drupal\social_scroll;

/**
 * Interface SocialScrollManagerInterface.
 */
interface SocialScrollManagerInterface {

  /**
   * The module name.
   */
  const MODULE_NAME = 'social_scroll';

  /**
   * Get all available views from social infinite scroll settings.
   *
   * @return array
   *   All available views from social infinite scroll settings.
   */
  public function getAllAvailableViews();

  /**
   * Get only enabled views from social infinite scroll settings.
   *
   * @return array
   *   Enabled views from social infinite scroll settings.
   */
  public function getEnabledViews();

  /**
   * Get blocked views.
   *
   * @return array
   *   Some system and distro views.
   */
  public function getBlockedViews();

}
