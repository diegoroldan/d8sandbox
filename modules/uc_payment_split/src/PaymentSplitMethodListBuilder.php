<?php

namespace Drupal\uc_payment_split;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\uc_payment_split\Plugin\PaymentSplitMethodManager;
use Drupal\uc_payment\Plugin\PaymentMethodManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of payment method configuration entities.
 */
class PaymentSplitMethodListBuilder extends DraggableListBuilder {

  /**
   * The payment method manager.
   *
   * @var \Drupal\uc_payment\Plugin\PaymentMethodManager
   */
  protected $paymentSplitMethodManager;

  /**
   * Constructs a new PaymentSplitMethodListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\uc_payment_split\Plugin\PaymentSplitMethodManager $payment_method_manager
   *   The payment method plugin manager.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, PaymentMethodManager $payment_split_method_manager) {
    parent::__construct($entity_type, $storage);
    $this->paymentSplitMethodManager = $payment_split_method_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(  
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('plugin.manager.uc_payment.method')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uc_payment_split_methods_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = [
      'data' => $this->t('Payment method'),
    ];
    $header['plugin'] = [
      'data' => $this->t('Type'),
      'class' => [RESPONSIVE_PRIORITY_LOW],
    ];
    $header['status'] = [
      'data' => $this->t('Status'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $definition = $entity->getPlugin()->getPluginDefinition();
    $row['plugin']['#markup'] = $definition['name'];
    $row['status']['#markup'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    // Locked payment methods may not be deleted.
    if (isset($operations['delete']) && $entity->isLocked()) {
      unset($operations['delete']);
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $options = array_map(function ($definition) {
      return $definition['name'];
    }, array_filter($this->paymentSplitMethodManager->getDefinitions(), function ($definition) {
      return !$definition['no_ui'];
    }));

    if ($options) {
      uasort($options, 'strnatcasecmp');

      $form['add'] = [
        '#type' => 'details',
        '#title' => $this->t('Add payment split method'),
        '#open' => TRUE,
        '#attributes' => [
          'class' => ['container-inline'],
        ],
      ];
      $form['add']['payment_split_method_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Type'),
        '#empty_option' => $this->t('- Choose -'),
        '#options' => $options,
      ];
      $form['add']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add payment method'),
        '#validate' => ['::validateAddPaymentSplitMethod'],
        '#submit' => ['::submitAddPaymentSplitMethod'],
        '#limit_validation_errors' => [['payment_split_method_type']],
      ];
    }

    $form = parent::buildForm($form, $form_state);
    $form[$this->entitiesKey]['#empty'] = $this->t('No payment methods have been configured.');

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->messenger()->addMessage($this->t('The configuration options have been saved.'));
  }

  /**
   * Form validation handler for adding a new payment method.
   */
  public function validateAddPaymentSplitMethod(array &$form, FormStateInterface $form_state) {
    if ($form_state->isValueEmpty('payment_split_method_type')) {
      $form_state->setErrorByName('payment_split_method_type', $this->t('You must select the new payment method type.'));
    }
  }

  /**
   * Form submission handler for adding a new payment method.
   */
  public function submitAddPaymentSplitMethod(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect(
      'entity.uc_payment_split_method.add_form',
      ['plugin_id' => $form_state->getValue('payment_split_method_type')]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['description'] = [
      '#markup' => '<p>' . $this->t('By default, only the "No payment required" payment method is listed here. To see additional payment methods you must <a href=":install">install additional modules</a>. The "Payment Method Pack" module that comes with Ubercart provides "Check" and "COD" payment methods. The "Credit Card" module that comes with Ubercart provides a credit card payment method, although you will need an additional module to provide a payment gateway for your credit card. For more information about payment methods and settings please read the <a href=":doc">Ubercart Documentation</a>.', [':install' => Url::fromRoute('system.modules_list', [], ['fragment' => 'edit-modules-ubercart-payment'])->toString(), ':doc' => Url::fromUri('https://www.drupal.org/docs/8/modules/ubercart')->toString()]) . '</p><p>' . $this->t('The order of methods shown below is the order those methods will appear on the checkout page. To re-order, drag the method to its desired location using the drag icon then save the configuration using the button at the bottom of the page.') . '</p>',
    ];
    $build += parent::render();

    return $build;
  }

}
