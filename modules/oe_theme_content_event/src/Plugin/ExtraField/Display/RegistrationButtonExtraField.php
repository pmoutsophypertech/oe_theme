<?php

declare(strict_types = 1);

namespace Drupal\oe_theme_content_event\Plugin\ExtraField\Display;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oe_content_event\EventNodeWrapper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extra field displaying the event registration button.
 *
 * @ExtraFieldDisplay(
 *   id = "oe_theme_content_event_registration_button",
 *   label = @Translation("Registration button"),
 *   bundles = {
 *     "node.oe_event",
 *   },
 *   visible = true
 * )
 */
class RegistrationButtonExtraField extends RegistrationDateAwareExtraFieldBase {

  /**
   * Entity view builder object.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected $viewBuilder;

  /**
   * Date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * RegistrationButtonExtraField constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity view builder object.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   Date formatter service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TimeInterface $time, EntityTypeManagerInterface $entity_type_manager, DateFormatterInterface $date_formatter) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $time);
    $this->viewBuilder = $entity_type_manager->getViewBuilder('node');
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('datetime.time'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(ContentEntityInterface $entity) {
    $event = new EventNodeWrapper($entity);

    // If event has no registration information then don't display anything.
    if (!$event->hasRegistration()) {
      return [];
    }

    /** @var \Drupal\link\Plugin\Field\FieldType\LinkItem $link */
    $link = $entity->get('oe_event_registration_url')->first();

    // Set default registration button values.
    $build = [
      '#theme' => 'oe_theme_content_event_registration_button',
      '#label' => t('Register'),
      '#url' => $link->getUrl()->toString(),
      '#description' => t('Register here'),
      '#enabled' => FALSE,
    ];

    // Registration is active.
    if ($event->isRegistrationPeriodActive($this->requestDateTime) && $event->isRegistrationOpen()) {
      $date_diff = $this->dateFormatter->formatDiff($this->requestDateTime->getTimestamp(), $event->getRegistrationEndDate()->getTimestamp(), ['granularity' => 1]);
      $build['#description'] = t('Book your seat, @time_left left to register.', [
        '@time_left' => $date_diff,
      ]);
      $build['#enabled'] = TRUE;

      return $build;
    }

    // Registration yet has to come.
    if ($event->isRegistrationPeriodYetToCome($this->requestDateTime)) {
      $build['#label'] = t('Registration will open on @start_date, until @end_date.', [
        '@start_date' => $this->dateFormatter->format($event->getRegistrationStartDate()->getTimestamp(), 'oe_event_date_hour'),
        '@end_date' => $this->dateFormatter->format($event->getRegistrationEndDate()->getTimestamp(), 'oe_event_date_hour'),
      ]);
      $build['#description'] = t('Registration will open on @date', [
        '@date' => $this->dateFormatter->format($event->getRegistrationStartDate()->getTimestamp(), 'oe_event_long_date_hour'),
      ]);

      return $build;
    }

    // Registration period is over.
    if ($event->isRegistrationPeriodOver($this->requestDateTime)) {
      $build['#label'] = t('Registration period ended on @date', [
        '@date' => $this->dateFormatter->format($event->getRegistrationEndDate()->getTimestamp(), 'oe_event_long_date_hour'),
      ]);
      $build['#description'] = t('Registration for this event has ended.');

      return $build;
    }

    // Registration period is closed.
    if ($event->isRegistrationClosed()) {
      $build['#label'] = t('Registration is now closed.');
      $build['#description'] = t('Registration is now closed for this event.');

      return $build;
    }

    $this->applyRegistrationDateMaxAge($build, $event);
    return $build;
  }

}
