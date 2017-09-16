<?php

namespace Drupal\probo\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'ProboActiveBuildBlock' block.
 *
 * @Block(
 *  id = "probo_active_build_block",
 *  admin_label = @Translation("Probo Active Build Block"),
 * )
 */
class ProboActiveBuildBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'display_repository_with_link' => 1,
      'display_pull_request_with_link' => 1,
      'title_links_to' => $this->t('Details Of Build Process'),
      'title_text_classes' => 'title-text',
      'title_anchor_classes' => 'title-text-link',
      'display_repository_text_classes' => 'repository-text',
      'display_repository_anchor_classes' => 'repository-text-link',
      'display_pull_request_text_classes' => 'pull-request-text',
      'display_pull_request_anchor_classes' => 'pull-request-text-link',
      'display_probo_link_text_class' => 'probo-text',
      'display_probo_link_anchor_class' => 'probo-anchor',
    ] + parent::defaultConfiguration();
 }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    // Retrieve existing configuration for this block.
    $config = $this->getConfiguration();
    $form['title'] = [
      '#type' => 'fieldset',
      '#title' => 'Title Link Configuration',
      '#weight' => 0,
    ];
    $form['repository'] = [
      '#type' => 'fieldset',
      '#title' => 'Repository Link Configuration',
      '#weight' => 1,
    ];
    $form['pull_request'] = [
      '#type' => 'fieldset',
      '#title' => 'Pull Request Link Configuration',
      '#weight' => 2,
    ];
    $form['probo'] = [
      '#type' => 'fieldset',
      '#title' => 'Probo Link Configuration',
      '#weight' => 3,
    ];
    $form['title']['title_links_to'] = [
      '#type' => 'select',
      '#title' => $this->t('Title Links To'),
      '#description' => $this->t('The place where the title should link to.'),
      '#options' => ['Details Of Build Process' => $this->t('Details Of Build Process'),
                     'Pull Request' => $this->t('Pull Request'),
                     'Probo Build' => $this->t('Probo Build')],
      '#default_value' => $config['title_links_to'],
      '#size' => 1,
      '#weight' => 0,
    ];
    $form['title']['title_text_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title Text Class'),
      '#description' => $this->t('Classes to be applied to the general title text.'),
      '#default_value' => $config['title_text_classes'],
      '#weight' => 0,
    ];
    $form['title']['title_anchor_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title Anchor Class'),
      '#description' => $this->t('Classes to be applied to the general title link anchor.'),
      '#default_value' => $config['title_anchor_classes'],
      '#weight' => 2,
    ];
    
    $form['repository']['display_repository_with_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display Repository With Link'),
      '#description' => $this->t('If you wish to display a link to the repository where this pull request came from.'),
      '#default_value' => $config['display_repository_with_link'],
      '#weight' => '0',
    ];
    $form['repository']['display_repository_text_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Display Repository Text Class'),
      '#description' => $this->t('Classes to be applied to the repository text.'),
      '#default_value' => $config['display_repository_text_classes'],
      '#weight' => 1,
    ];
    $form['repository']['display_repository_anchor_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Display Repository Anchor Class'),
      '#description' => $this->t('Classes to be applied to the repository link anchor.'),
      '#default_value' => $config['display_repository_anchor_classes'],
      '#weight' => 2,
    ];

    $form['pull_request']['display_pull_request_with_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display Pull Request With Link'),
      '#description' => $this->t('If you wish to display a link to the pull request that generated this probo instance.'),
      '#default_value' => $config['display_pull_request_with_link'],
      '#weight' => '1',
    ];
    $form['pull_request']['display_pull_request_text_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Display Pull Request Text Class'),
      '#description' => $this->t('Classes to be applied to the pull request text.'),
      '#default_value' => $config['display_pull_request_text_classes'],
      '#weight' => 1,
    ];
    $form['pull_request']['display_pull_request_anchor_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Display Pull Request Anchor Class'),
      '#description' => $this->t('Classes to be applied to the pull request link anchor.'),
      '#default_value' => $config['display_pull_request_anchor_classes'],
      '#weight' => 2,
    ];
    
    $form['probo']['display_probo_link_text_class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Display Probo Text Link Class'),
      '#description' => $this->t('Classes to be applied to the Probo link text.'),
      '#default_value' => $config['display_probo_link_text_class'],
      '#weight' => 0,
    ];
    $form['probo']['display_probo_link_anchor_class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Display Probo Text Anchor Class'),
      '#description' => $this->t('Classes to be applied to the Probo link anchor.'),
      '#default_value' => $config['display_probo_link_anchor_class'],
      '#weight' => 1,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {

    $title = $form_state->getValue('title');
    $this->configuration['title_links_to'] = $title['title_links_to'];
    $this->configuration['title_text_classes'] = $title['title_text_classes'];
    $this->configuration['title_anchor_classes'] = $title['title_anchor_classes'];

    $repository = $form_state->getValue('repository');
    $this->configuration['display_repository_with_link'] = $repository['display_repository_with_link'];
    $this->configuration['display_repository_text_classes'] = $repository['display_repository_text_classes'];
    $this->configuration['display_repository_anchor_classes'] = $repository['display_repository_anchor_classes'];

    $pull_request = $form_state->getValue('pull_request');
    $this->configuration['display_pull_request_with_link'] = $pull_request['display_pull_request_with_link'];
    $this->configuration['display_pull_request_text_classes'] = $pull_request['display_pull_request_text_classes'];
    $this->configuration['display_pull_request_anchor_classes'] = $pull_request['display_pull_request_anchor_classes'];

    $probo = $form_state->getValue('probo');
    $this->configuration['display_probo_link_text_class'] = $probo['display_probo_link_text_class'];
    $this->configuration['display_probo_link_anchor_class'] = $probo['display_probo_link_anchor_class'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Retrieve existing configuration for this block.
    $block_config = $this->getConfiguration();

    // Get the builds from our database.
    $query = \Drupal::database()->select('probo_builds', 'pb')
      ->fields('pb', ['id', 'bid', 'repository', 'owner', 'service', 'pull_request_name', 
        'author_name', 'pull_request_url'])
      ->condition('active', 1);
    $builds = $query->execute()->fetchAllAssoc('id');

    // Assemble the build id's into an array to be iterated through in the template.
    if (empty(count($builds))) {
      return [
        '#prefix' => '<span class="' . $block_config['title_text_classes'] . '">',
        '#markup' => 'There are no active Probo builds to display.',
        '#suffix' => '</span>',
      ];
    }
    else {
      $config = \Drupal::config('probo.probosettings');
      return [
        '#theme' => 'probo_active_build_block',
        '#probo_builds_domain' => $config->get('probo_builds_domain'),
        '#builds' => $builds,
        '#title_links_to' => $block_config['title_links_to'],
        '#title_class' => $block_config['title_classes'],
        '#title_anchor_class' => $block_config['title_anchor_classes'],
        '#repository' => $block_config['display_repository_with_link'],
        '#repository_class' => $block_config['display_repository_text_classes'],
        '#repository_anchor' => $block_config['display_repository_anchor_classes'],
        '#pull_request' => $block_config['display_pull_request_with_link'],
        '#pull_request_class' => $block_config['display_pull_request_text_classes'],
        '#pull_request_anchor' => $block_config['display_pull_request_anchor_classes'],
        '#probo_class' => $block_config['display_probo_link_text_class'],
        '#probo_anchor' => $block_config['display_probo_link_anchor_class'],
      ];
    }
  }
}
