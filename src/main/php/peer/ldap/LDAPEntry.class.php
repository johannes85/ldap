<?php namespace peer\ldap;

/**
 * Wraps LDAP entry
 *
 * @purpose  Represent a single entry
 * @see      xp://peer.ldap.LDAPSearchResult
 * @see      xp://peer.ldap.LDAPClient
 * @test     xp://net.xp_framework.unittest.peer.LDAPEntryTest
 */
class LDAPEntry extends \lang\Object {
  public
    $dn=          '',
    $attributes=  array();
  
  protected
    $_ans=        array();
    
  /**
   * Constructor
   *
   * @param   string dn default NULL "distinct name"
   * @param   var[] attrs default array()
   */
  public function __construct($dn= null, $attrs= array()) {
    $this->dn= $dn;
    if (sizeof($attrs)) {
      $this->attributes= array_change_key_case($attrs, CASE_LOWER);
      $this->_ans= array_combine(array_keys($this->attributes), array_keys($attrs));
    }
  }

  /**
   * Create LDAPEntry from a given DN and associative
   * array
   *
   * @param   string dn
   * @param   [:string] data
   * @return  peer.ldap.LDAPEntry
   */
  protected static function _create($dn, array $data) {
    $e= new self($dn);

    foreach ($data as $key => $value) {
      if ('count' === $key || is_int($key)) continue;

      // Store case-preserved version of key name in _ans array        
      $lkey= strtolower($key);
      $e->_ans[$lkey]= $key;

      $e->attributes[$lkey]= $value;
      unset($e->attributes[$lkey]['count']);
    }

    return $e;
  }
      
  /**
   * Creates an LDAP from the raw return data of PHP's ldap_* functions
   * Also performs decoding on the attributes.
   *
   * @param   resource handle ldap connection
   * @param   resource res ldap result resource
   * @return  peer.ldap.LDAPEntry object
   */
  public static function fromResource($handle, $res) {
    return self::_create(ldap_get_dn($handle, $res), ldap_get_attributes($handle, $res));
  }
  
  /**
   * Creates an LDAP from the raw return data of PHP's ldap_* functions
   * Also performs decoding on the attributes.
   *
   * @param   var data return value from ldap_* functions
   * @return  peer.ldap.LDAPEntry object
   */
  public static function fromData($data) {
    $dn= $data['dn']; unset($data['dn']);
    return self::_create($dn, $data);
  }
  
  /**
   * Set this entry's DN (distinct name)
   *
   * @param   string dn
   */
  public function setDN($dn) {
    $this->dn= $dn;
  }
  
  /**
   * Retrieve this entry's DN (distinct name)
   *
   * @return  string DN
   */
  public function getDN() {
    return $this->dn;
  }

  /**
   * Set attribute
   *
   * @param   string key
   * @param   var value
   */
  public function setAttribute($key, $value) {
    $this->_ans[strtolower($key)]= $key;
    $this->attributes[strtolower($key)]= (array)$value;
  }
  
  /**
   * Retrieve an attribute - an offset may be supplied to define
   * the values offset within the attribute. If -1 (the default)
   * is supplied, an array of attribute values is returned.
   *
   * Note: If the value does not exist, NULL is returned
   *
   * @param   string key
   * @param   int idx default -1
   * @return  var attribute
   */
  public function getAttribute($key, $idx= -1) {
    $lkey= strtolower($key);
    return (($idx >= 0)
      ? (isset($this->attributes[$lkey][$idx]) ? $this->attributes[$lkey][$idx] : null)
      : (isset($this->attributes[$lkey]) ? $this->attributes[$lkey] : null)
    );
  }
  
  /**
   * Retrieve all attributes
   *
   * @return  array
   */
  public function getAttributes() {
    return $this->attributes;
  }
  
  /**
   * Retrieve whether this entry is of a given object class.
   *
   * Note: The given objectClass is treated case-sensitive!
   *
   * @param   string objectClass
   * @return  bool
   */
  public function isA($objectClass) {
    return in_array($objectClass, $this->attributes['objectclass']);
  }

  /**
   * Retrieve a string representation of this object
   *
   * @return  string
   */
  public function toString() {
    $s= sprintf("%s@DN(%s){\n", $this->getClassName(), $this->getDN());
    foreach ($this->attributes as $name => $attr) {
      $s.= sprintf("  [%-20s] %s\n", $this->_ans[$name], implode(', ', $attr));
    }
    return $s."}\n";
  }
}
