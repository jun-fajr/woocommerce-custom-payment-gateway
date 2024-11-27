<?php
if (!defined('ABSPATH')) {
  exit;
}

class WC_Custom_Gateway_Helper
{

  /**
   * Generate form field untuk konfigurasi.
   */
  public static function generate_form_fields($fields)
  {
    $html = '';

    foreach ($fields as $key => $field) {
      $label = $field['title'];
      $type = $field['type'];
      $description = isset($field['description']) ? $field['description'] : '';
      $default = isset($field['default']) ? $field['default'] : '';

      $html .= "<p><label for='{$key}'>{$label}</label>";
      $html .= "<input type='{$type}' name='{$key}' id='{$key}' value='{$default}' />";
      if ($description) {
        $html .= "<small>{$description}</small>";
      }
      $html .= "</p>";
    }

    return $html;
  }
}
