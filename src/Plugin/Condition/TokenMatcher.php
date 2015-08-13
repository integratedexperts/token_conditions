<?php

/**
 * @file
 * Contains \Drupal\node\Plugin\Condition\TokenMatcher.
 */

namespace Drupal\token_conditions\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Entity;
/**
 * Provides a 'Token Matcher' condition.
 *
 * @Condition(
 *   id = "token_matcher",
 *   label = @Translation("Token Matcher")
 * )
 *
 */
class TokenMatcher extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * Creates a new TokenMatcher instance.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_storage
   *   The entity storage.
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form_id = $form_state->getBuildInfo()['form_id'];

    // @todo How to add multiple conditions on block form?
    // @see
    $form['token_match'] = array(
      '#title' => $this->t('Token String'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['token_match'],
      '#description' => $this->t('Enter token or string with multiple tokens'),
    );
    $form['check_empty'] = array(
      '#type' => 'checkbox',
      '#title' => t('Check if value is empty'),
      '#description' => t(''),
      '#default_value' => $this->configuration['check_empty'],
    );
    $form['value_match'] = array(
      '#title' => $this->t('Value String'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['value_match'],
      '#description' => $this->t('Enter string to check against. This can also contain tokens'),
    );
    $form['use_regex'] = array(
      '#type' => 'checkbox',
      '#title' => t('Use regex match'),
      '#description' => t(''),
      '#default_value' => $this->configuration['use_regex'],
      '#states' => array(
        'invisible' => array(
          ':input[name="settings[check_empty]"]' => array('checked' => TRUE),
        ),
      ),
    );
    return parent::buildConfigurationForm($form, $form_state);
  }


  /**
   * {@inheritdoc}
   */
  public function summary() {
    return $this->t('@token_match = @value_match',
      array(
        '@token_match' => $this->configuration['token_match'],
        '@value_match' => $this->configuration['value_match']
      )
    );
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    $token_data = $this->getTokenData();
    $token_service = \Drupal::token();
    $token_replaced = $token_service->replace($this->configuration['token_match'], $token_data);
    $value_replace = $token_service->replace($this->configuration['value_match'], $token_data);
    return $token_replaced == $value_replace;
  }

  private function getTokenType(ContentEntityType $entity_type) {
    return  $entity_type->get('token type') ? $entity_type->get('token type') : $entity_type->id();
  }


  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['token_match'] = $form_state->getValue('token_match');
    $this->configuration['value_match'] = $form_state->getValue('value_match');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'token_match' => '',
      'value_match' => '',
      'check_empty' => 0,
      'use_regex' => 0,
    ) + parent::defaultConfiguration();
  }

  /**
   * @return array
   */
  protected function getTokenData() {
    $token_data = [];
    $allEntities = \Drupal::entityManager()->getDefinitions();
    foreach ($allEntities as $entity_type => $entity_type_info) {
      if ($entity_type_info instanceof ContentEntityType) {
        if ($entity = $this->getContextValue($entity_type)) {
          $token_type = $this->getTokenType($entity_type_info);
        }


      }
    }

    $contexts = $this->getContexts();

    $token_data = [];
    $attributes = \Drupal::request()->attributes->all();
    foreach ($attributes as $attribute_key => $attribute) {
      if (is_object($attribute) && $attribute instanceof ContentEntityInterface) {
        /**
         * @var ContentEntityInterface $entity ;
         */
        $entity = $attribute;
        $token_data[$this->getTokenType($entity)] = $entity;
      }
    }
    return $token_data;
  }

}
