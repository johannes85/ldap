<?php namespace peer\ldap\filter;

class GreaterThanEqualsFilter implements Filter {
  public $attribute, $value;

  public function __construct($attribute, $value) {
    $this->attribute= $attribute;
    $this->value= $value;
  }
}