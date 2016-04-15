<?php namespace peer\ldap;

// Search scopes
define('LDAP_SCOPE_BASE',     0x0000);
define('LDAP_SCOPE_ONELEVEL', 0x0001);
define('LDAP_SCOPE_SUB',      0x0002);

use peer\ConnectException;

/**
 * LDAP client
 * 
 * Example:
 * <code>
 *   xp::sapi('cli');
 *   uses('peer.ldap.LDAPClient');
 *   
 *   $l= new LDAPClient('ldap.openldap.org');
 *   try {
 *     $l->setOption(LDAP_OPT_PROTOCOL_VERSION, 3);
 *     $l->connect();
 *     $l->bind();
 *     $res= $l->search(
 *       'ou=People,dc=OpenLDAP,dc=Org', 
 *       '(objectClass=*)'
 *     );
 *   } catch (ConnectException $e) {
 *     $e->printStackTrace();
 *     exit(-1);
 *   } catch (LDAPException $e) {
 *     $e->printStackTrace();
 *     exit(-1);
 *   }
 *     
 *   Console::writeLinef('===> %d entries found', $res->numEntries());
 *   while ($entry= $res->getNextEntry()) {
 *     Console::writeLine('---> ', $entry->toString());
 *   }
 *   
 *   // Disconnect
 *   $l->close();
 * </code>
 *
 * @deprecated Use LDAPConnection instead
 * @see      php://ldap
 * @see      http://developer.netscape.com/docs/manuals/directory/41/ag/
 * @see      http://developer.netscape.com/docs/manuals/dirsdk/jsdk40/contents.htm
 * @see      http://perl-ldap.sourceforge.net/doc/Net/LDAP/
 * @see      http://ldap.akbkhome.com/
 * @see      rfc://2251
 * @see      rfc://2252
 * @see      rfc://2253
 * @see      rfc://2254
 * @see      rfc://2255
 * @see      rfc://2256
 * @ext      ldap
 * @test     xp://net.xp_framework.unittest.peer.LDAPTest
 * @purpose  LDAP client
 */
class LDAPClient extends \lang\Object {
  const SCOPE_BASE      = 0x0000;
  const SCOPE_ONELEVEL  = 0x0001;
  const SCOPE_SUB       = 0x0002;

  public 
    $host,
    $port;
    
  public
    $_hdl;

  static function __static() {
    \lang\XPClass::forName('peer.ldap.LDAPException');  // Error codes
  }

  /**
   * Constructor
   *
   * @param   string host default 'localhost' LDAP server
   * @param   int port default 389 Port
   */
  public function __construct($host= 'localhost', $port= 389) {
    $this->host= $host;
    $this->port= $port;
  }
  
  /**
   * Connect to the LDAP server
   *
   * @return  resource LDAP resource handle
   * @throws  peer.ConnectException
   */
  public function connect() {
    if ($this->isConnected()) return true;  // Already connected
    if (false === ($this->_hdl= ldap_connect($this->host, $this->port))) {
      throw new ConnectException('Cannot connect to '.$this->host.':'.$this->port);
    }
    
    return $this->_hdl;
  }
  
  /**
   * Bind
   *
   * @param   string user default NULL
   * @param   string pass default NULL
   * @return  bool success
   * @throws  peer.ldap.LDAPException
   * @throws  peer.ConnectException
   */
  public function bind($user= null, $pass= null) {
    if (false === ($res= ldap_bind($this->_hdl, $user, $pass))) {
      switch ($error= ldap_errno($this->_hdl)) {
        case -1: case LDAP_SERVER_DOWN:
          throw new ConnectException('Cannot connect to '.$this->host.':'.$this->port);
        
        default:
          throw new LDAPException('Cannot bind for "'.$user.'"', $error);
      }
    }
    
    return $res;
  }
  
  /**
   * Sets an ldap option value
   *
   * @param   int option
   * @param   var value
   * @return  bool success
   */
  public function setOption($option, $value) {
    if (false === ($res= ldap_set_option ($this->_hdl, $option, $value))) {
      throw (new LDAPException ('Cannot set value "'.$option.'"', ldap_errno($this->_hdl)));
    }
    
    return $res;
  }

  /**
   * Retrieve ldap option value
   *
   * @param   int option
   * @return  var value
   */    
  public function getOption($option) {
    if (false === ($res= ldap_get_option ($this->_hdl, $option, $value))) {
      throw (new LDAPException ('Cannot get value "'.$option.'"', ldap_errno($this->_hdl)));
    }
    
    return $value;
  }
  
  /**
   * Checks whether the connection is open
   *
   * @return  bool true, when we're connected, false otherwise (OK, what else?:))
   */
  public function isConnected() {
    return is_resource($this->_hdl);
  }
  
  /**
   * Closes the connection
   *
   * @see     php://ldap_close
   * @return  bool success
   */
  public function close() {
    if (!$this->isConnected()) return true;
    
    ldap_unbind($this->_hdl);
    $this->_hdl= null;
  }
  
  /**
   * Perform an LDAP search with scope LDAP_SCOPE_SUB
   *
   * @param   string base_dn
   * @param   string filter
   * @param   array attributes default array()
   * @param   int attrsonly default 0,
   * @param   int sizelimit default 0
   * @param   int timelimit default 0 Time limit, 0 means no limit
   * @param   int deref one of LDAP_DEREF_*
   * @return  peer.ldap.LDAPSearchResult search result object
   * @throws  peer.ldap.LDAPException
   * @see     php://ldap_search
   */
  public function search() {
    $args= func_get_args();
    array_unshift($args, $this->_hdl);
    if (false === ($res= call_user_func_array('ldap_search', $args))) {
      throw new LDAPException('Search failed', ldap_errno($this->_hdl));
    }
    
    return new LDAPSearchResult(new LDAPEntries($this->_hdl, $res));
  }
  
  /**
   * Perform an LDAP search specified by a given filter.
   *
   * @param   peer.ldap.LDAPQuery filter
   * @return  peer.ldap.LDAPSearchResult search result object
   */
  public function searchBy(LDAPQuery $filter) {
    static $methods= array(
      self::SCOPE_BASE     => 'ldap_read',
      self::SCOPE_ONELEVEL => 'ldap_list',
      self::SCOPE_SUB      => 'ldap_search'
    );
    
    if (empty($methods[$filter->getScope()]))
      throw new \lang\IllegalArgumentException('Scope '.$args[0].' not supported');
    
    if (false === ($res= @call_user_func_array(
      $methods[$filter->getScope()], array(
      $this->_hdl,
      $filter->getBase(),
      $filter->getFilter(),
      $filter->getAttrs(),
      $filter->getAttrsOnly(),
      $filter->getSizeLimit(),
      $filter->getTimelimit(),
      $filter->getDeref()
    )))) {
      throw new LDAPException('Search failed', ldap_errno($this->_hdl));
    }

    // Sort results by given sort attributes
    if ($filter->getSort()) foreach ($filter->getSort() as $sort) {
      ldap_sort($this->_hdl, $res, $sort);
    }
    return new LDAPSearchResult(new LDAPEntries($this->_hdl, $res));
  }
  
  /**
   * Perform an LDAP search with a scope
   *
   * @param   int scope search scope, one of the LDAP_SCOPE_* constants
   * @param   string base_dn
   * @param   string filter
   * @param   array attributes default NULL
   * @param   int attrsonly default 0,
   * @param   int sizelimit default 0
   * @param   int timelimit default 0 Time limit, 0 means no limit
   * @param   int deref one of LDAP_DEREF_*
   * @return  peer.ldap.LDAPSearchResult search result object
   * @throws  peer.ldap.LDAPException
   * @see     php://ldap_search
   */
  public function searchScope() {
    $args= func_get_args();
    switch ($args[0]) {
      case self::SCOPE_BASE: $func= 'ldap_read'; break;
      case self::SCOPE_ONELEVEL: $func= 'ldap_list'; break;
      case self::SCOPE_SUB: $func= 'ldap_search'; break;
      default: throw new \lang\IllegalArgumentException('Scope '.$args[0].' not supported');
    }
    $args[0]= $this->_hdl;
    if (false === ($res= call_user_func_array($func, $args))) {
      throw new LDAPException('Search failed', ldap_errno($this->_hdl));
    }
    
    return new LDAPSearchResult(new LDAPEntries($this->_hdl, $res));
  }
  
  /**
   * Read an entry
   *
   * @param   peer.ldap.LDAPEntry entry specifying the dn
   * @return  peer.ldap.LDAPEntry entry
   * @throws  lang.IllegalArgumentException
   * @throws  peer.ldap.LDAPException
   */
  public function read(LDAPEntry $entry) {
    $res= ldap_read($this->_hdl, $entry->getDN(), 'objectClass=*', array(), false, 0);
    if (LDAP_SUCCESS != ldap_errno($this->_hdl)) {
      throw new LDAPException('Read "'.$entry->getDN().'" failed', ldap_errno($this->_hdl));
    }

    $entry= ldap_first_entry($this->_hdl, $res);
    return LDAPEntry::create(ldap_get_dn($this->_hdl, $entry), ldap_get_attributes($this->_hdl, $entry));
  }
  
  /**
   * Check if an entry exists
   *
   * @param   peer.ldap.LDAPEntry entry specifying the dn
   * @return  bool TRUE if the entry exists
   */
  public function exists(LDAPEntry $entry) {
    $res= ldap_read($this->_hdl, $entry->getDN(), 'objectClass=*', array(), false, 0);
    
    // Check for certain error code (#32)
    if (LDAP_NO_SUCH_OBJECT == ldap_errno($this->_hdl)) {
      return false;
    }
    
    // Check for other errors
    if (LDAP_SUCCESS != ldap_errno($this->_hdl)) {
      throw new LDAPException('Read "'.$entry->getDN().'" failed', ldap_errno($this->_hdl));
    }
    
    // No errors occurred, requested object exists
    ldap_free_result($res);
    return true;
  }
  
  /**
   * Add an entry
   *
   * @param   peer.ldap.LDAPEntry entry
   * @return  bool success
   * @throws  lang.IllegalArgumentException when entry parameter is not an LDAPEntry object
   * @throws  peer.ldap.LDAPException when an error occurs during adding the entry
   */
  public function add(LDAPEntry $entry) {
    
    // This actually returns NULL on failure, not FALSE, as documented
    if (null == ($res= ldap_add(
      $this->_hdl, 
      $entry->getDN(), 
      $entry->getAttributes()
    ))) {
      throw new LDAPException('Add for "'.$entry->getDN().'" failed', ldap_errno($this->_hdl));
    }
    
    return $res;
  }

  /**
   * Modify an entry. 
   *
   * Note: Will do a complete update of all fields and can be quite slow
   * TBD(?): Be more intelligent about what to update?
   *
   * @param   peer.ldap.LDAPEntry entry
   * @return  bool success
   * @throws  lang.IllegalArgumentException when entry parameter is not an LDAPEntry object
   * @throws  peer.ldap.LDAPException when an error occurs during adding the entry
   */
  public function modify(LDAPEntry $entry) {
    if (false == ($res= ldap_modify(
      $this->_hdl,
      $entry->getDN(),
      $entry->getAttributes()
    ))) {
      throw new LDAPException('Modify for "'.$entry->getDN().'" failed', ldap_errno($this->_hdl));
    }
    
    return $res;
  }

  /**
   * Delete an entry
   *
   * @param   peer.ldap.LDAPEntry entry
   * @return  bool success
   * @throws  lang.IllegalArgumentException when entry parameter is not an LDAPEntry object
   * @throws  peer.ldap.LDAPException when an error occurs during adding the entry
   */
  public function delete(LDAPEntry $entry) {
    if (false == ($res= ldap_delete(
      $this->_hdl,
      $entry->getDN()
    ))) {
      throw new LDAPException('Delete for "'.$entry->getDN().'" failed', ldap_errno($this->_hdl));
    }
    
    return $res;
  }

  /**
   * Add an attribute to an entry
   *
   * @param   peer.ldap.LDAPEntry entry
   * @param   string name
   * @param   var value
   * @return  bool
   */
  public function addAttribute(LDAPEntry $entry, $name, $value) {
    if (false == ($res= ldap_mod_add(
      $this->_hdl,
      $entry->getDN(),
      array($name => $value)
    ))) {
      throw new LDAPException('Add attribute for "'.$entry->getDN().'" failed', ldap_errno($this->_hdl));
    }
    
    return $res;
  }

  /**
   * Delete an attribute from an entry
   *
   * @param   peer.ldap.LDAPEntry entry
   * @param   string name
   * @return  bool
   */
  public function deleteAttribute(LDAPEntry $entry, $name) {
    if (false == ($res= ldap_mod_del(
      $this->_hdl,
      $entry->getDN(),
      $name
    ))) {
      throw new LDAPException('Delete attribute for "'.$entry->getDN().'" failed', ldap_errno($this->_hdl));
    }
    
    return $res;
  }

  /**
   * Add an attribute to an entry
   *
   * @param   peer.ldap.LDAPEntry entry
   * @param   string name
   * @param   var value
   * @return  bool
   */
  public function replaceAttribute(LDAPEntry $entry, $name, $value) {
    if (false == ($res= ldap_mod_replace(
      $this->_hdl,
      $entry->getDN(),
      array($name => $value)
    ))) {
      throw new LDAPException('Add attribute for "'.$entry->getDN().'" failed', ldap_errno($this->_hdl));
    }
    
    return $res;
  }
}
