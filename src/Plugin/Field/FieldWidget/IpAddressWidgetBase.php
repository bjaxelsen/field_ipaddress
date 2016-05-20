<?php

namespace Drupal\field_ipaddress\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Base class for the 'ipaddress_*' widgets.
 */
class IpAddressWidgetBase extends WidgetBase {
  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = array(
      'value' => $element + array(
        '#type' => 'textfield'
      )
    );
    $element['#element_validate'] = array(array(get_class($this), 'validateIpAddressElement'));

    // For easy access to validator, we include these settings here
    $element['ipv4_span'] = array(
      '#value' => $this->getSetting('ipv4_span')
    );
    $element['ipv6_span'] = array(
      '#value' => $this->getSetting('ipv6_span')
    );

    if (($value = $items[$delta]->getValue()) && !empty($value['ip_from'])) {
      $element['value']['#default_value'] = inet_ntop($value['ip_from']);
      if ($value['ip_to'] != $value['ip_from']) {
        $element['value']['#default_value'] .= ' - ' . inet_ntop($value['ip_to']);
      }
    }

    return $element;
  }

  /**
   * Custom validator
   *
   * @param $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param $form
   */
  public static function validateIpAddressElement(&$element, FormStateInterface $form_state, $form) {
    if (trim($element['value']['#value']) !== '') {
      // Get rid of spaces
      $value = str_replace(' ', '', $element['value']['#value']);
      // If a range, extract the parts
      $ip_parts = explode('-', $value);
      if (count($ip_parts) > 2) {
        $form_state->setError($element, t('Please provide a valid IP range as a span X.X.X.X - X.X.X.X.'));
        return;
      }
      if (!filter_var($ip_parts[0], FILTER_VALIDATE_IP)) {
        $form_state->setError($element, t('Please provide a valid IP address or IP range.'));
        return;
      }
      $packed_start = inet_pton($ip_parts[0]);
      if (!isset($ip_parts[1])) {
        $packed_end = $packed_start;
      }
      else {
        if (!filter_var($ip_parts[1], FILTER_VALIDATE_IP)) {
          $form_state->setError($element, t('Please provide a valid IP address or IP range.'));
          return;
        }
        $packed_end = inet_pton($ip_parts[1]);
      }
      // Validate type of IP is the same
      if (strlen($packed_start) != strlen($packed_end)) {
        $form_state->setError($element, t('Please provide the same type of IP addresses.'));
        return;
      }
      // Validate last is not lower than first
      if ($packed_start > $packed_end) {
        $form_state->setError($element, t('Please make sure start of IP interval is lower than end of interval.'));
        return;
      }
      // Validate span
      $is_ipv6 = (strlen($packed_start) == 16) ? 1 : 0;
      $max_span = $is_ipv6 ? $element['ipv6_span']['#value'] : $element['ipv4_span']['#value'];
      $start_number = unpack('C*', $packed_start);
      $end_number = unpack('C*', $packed_end);
      $difference = 0;
      $i = 0;
      while (count($start_number)) {
        $start_byte = array_pop($start_number);
        $end_byte = array_pop($end_number);
        if ($start_byte != $end_byte) {
          $factor = pow(256, $i);
          $difference += ($end_byte - $start_byte) * $factor;
        }
        $i++;
      }
      if ($difference > $max_span) {
        $form_state->setError($element, t('Please make sure the IP interval does not span more than @span numbers.', array('@span' => $max_span)));
        return;
      }
    }
  }


  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // Convert to storage format
    foreach ($values as &$item) {
      if (!empty($value = trim($item['value']))) {
          // Get rid of spaces
          $value = str_replace(' ', '', $value);
          // If a range, extract the parts
          $ip_parts = explode('-', $value);
          $item['ip_from'] = filter_var($ip_parts[0], FILTER_VALIDATE_IP) ? inet_pton($ip_parts[0]) : '';

          if (isset($ip_parts[1])) {
            $item['ip_to'] = filter_var($ip_parts[1], FILTER_VALIDATE_IP) ? inet_pton($ip_parts[1]) : '';
          }
          else {
            $item['ip_to'] = $item['ip_from'];
          }
          // IPv6 addresses as in_addr are 16 bytes, check if this is true
          $item['ipv6'] = (strlen($item['ip_from']) == 16) ? 1 : 0;
      }
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = parent::defaultSettings();

    $settings['ipv4_span'] = 65536; // 2^16
    $settings['ipv6_span'] = 16777216; // 2^24

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['ipv4_span'] = array(
      '#type' => 'textfield',
      '#title' => t('Maximum span for IPv4 addresses'),
      '#default_value' => $this->getSetting('ipv4_span'),
    );

    $element['ipv6_span'] = array(
      '#type' => 'textfield',
      '#title' => t('Maximum span for IPv6 addresses'),
      '#default_value' => $this->getSetting('ipv6_span'),
    );

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();

    $summary[] = t('Spans: @ipv4 (IPv4) / @ipv6 (IPv6)', array('@ipv4' => $this->getSetting('ipv4_span'), '@ipv6' => $this->getSetting('ipv6_span')));

    return $summary;
  }

}
