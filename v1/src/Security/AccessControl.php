<?php
/**
 * @author Ekene Ezeasor
 * @copyright 2021 Ekene Ezeasor
 */

namespace Security;

use CIRMS;

class AccessControl
{
  /** @property */
  private $dbconnect;
  public $user_id;
  public $perm_id;
  public $role_id;
  public $lastpermids = array();

  public function __construct()
  {
    $db = new \DBConnect;
    $this->dbconnect = $db->db_connect();
  }

  /**
  * Add permissions to the permission table
  * Group is used to identify the originating plugin
  * It skips existing permission names
  * @param array
  * @return mixed int|false Use $this->$lastpermids to list all the last inserted IDs on true
  * @example if (isset($ac->$lastpermids))
  *           foreach($ac->$lastpermids as $id)
  *             echo $id . "<br>";
  */
  public function addPermissions(array $permissions,$group="Other Permissions")
  {
    $group = CIRMS::sanitize($group);
    foreach ($permissions as $name => $description) :
      $name = CIRMS::sanitize($name);
      $description = CIRMS::sanitize($description);
      $table = "iems_ac_permissions";
      $values = "'{$name}','{$description}','{$group}'";
      $columns = "perm_name,perm_desc,perm_group";
      $name_exists = $this->selectSQL($table,"perm_name","perm_name='{$name}'");
      if (!$name_exists) {
        if ($this->insertSQL($table,$values,$columns)) {
          galaxy_log_activities("Add Permission","Added {$name} ({$description}) under <b>{$group}</b> group.");
          $this->$lastpermids[] = $this->getlastinsertID();
        } else {
          iems_log_errors("Error encoutered while adding {$name} ({$description}) under <b>{$group}</b> into {$table} against ({$columns})",get_class()." class (".__FUNCTION__ .")",$this->dbconnect->error);
        }
      }
    endforeach;
    if (is_array($this->$lastpermids)) {
      return $this->$lastpermids;
    } else {
      return false;
    }
  }

  /**
  * Add single permission to the permission table
  * Accepts value as a string
  * It returns the last inserted id
  */
  public function addSinglePermission($name, $description,$group="Other Permissions")
  {
    $group = CIRMS::sanitize($group);
    $name = CIRMS::sanitize($name);
    $description = CIRMS::sanitize($description);
    $table = "iems_ac_permissions";
    $values = "'{$name}','{$description}','{$group}'";
    $columns = "perm_name,perm_desc,perm_group";
    if ($this->insertSQL($table,$values,$columns)) {
      galaxy_log_activities("Add Permission","Added {$name} ({$description}) under <b>{$group}</b> group.");
      return $this->getlastinsertID();
    } else {
      iems_log_errors("Error encoutered while adding single {$name} ({$description}) under <b>{$group}</b> into {$table} against ({$columns})",get_class()." class (".__FUNCTION__ .")",$this->dbconnect->error);
      return FALSE;
    }
  }

  /**
   * Update single permission details
   * @param mixed
   * @return true|false
   */
  public function updatePermission(int $permid,string $name, string $description, string $group)
  {
    $permid = CIRMS::sanitize($permid);
    $name = CIRMS::sanitize($name);
    $description = CIRMS::sanitize($description);
    $group = CIRMS::sanitize($group);
    $what_to_set = "perm_name='$name', perm_desc='$description', perm_group='$group'";
    $where = "perm_id = {$permid} AND perm_group!='System'";
    $table = "iems_ac_permissions";
    $former = $this->selectSQL($table,"perm_name","perm_id='{$permid}'");
    $this->updateSQL($table,$what_to_set,$where);
    if ($this->affectedrowsSQL() > 0) {
      galaxy_log_activities("Update Permission","Updated {$former} to {$name} ({$description}) under <b>{$group}</b> group.");
      return TRUE;
    } else {
      iems_log_errors("Error encoutered while updating {$name} ({$description}) under <b>{$group}</b> into {$table} considering ({$where})",get_class()." class (".__FUNCTION__ .")",$this->dbconnect->error);
      return FALSE;
    }
  }

  /**
  * Delete permission details
  * Accepts value as an array
  * Returns true on success
  */
  public function deletePermission(array $name)
  {
    $x = 1;
    foreach ($name as $perm_name) {
      $perm_name = CIRMS::sanitize($perm_name);
      $table = "iems_ac_permissions";
      $table2 = "iems_ac_role_permissions";
      $permid = $this->getPermID($perm_name);
      $description = $this->selectSQL($table,"perm_desc","perm_id='{$permid}' AND perm_group!='System'");
      $group = $this->selectSQL($table,"perm_group","perm_id='{$permid}'");
      $where = "perm_id = {$permid}";
      $this->deleteSQL($table2,$where);
      $this->deleteSQL($table,$where);
      if ($this->affectedrowsSQL() > 0) {
        galaxy_log_activities("Delete Permission","Deleted {$permid} ({$perm_name} - {$description}) under <b>{$group}</b> group.");
        $count[] = $x;
      } else {
        iems_log_errors("Error encoutered while deleting {$permid} ({$perm_name} - {$description}) under <b>{$group}</b> from {$table} considering ({$where})",get_class()." class (".__FUNCTION__ .")",$this->dbconnect->error);
      }
      $x++;
    }
    if (is_array($count)) {
      return $count;
    } else {
      return false;
    }
  }

  /**
  * Get permission id
  * Accepts value as an string
  * Returns permission id on success
  */
  public function getPermID($perm_name)
  {
    $perm_name = CIRMS::sanitize($perm_name);
    $this->perm_id = $this->selectSQL("iems_ac_permissions","perm_id","perm_name='{$perm_name}'");
    return $this->perm_id;
  }

  /**
  * Get permission name
  * Accepts value as an integer
  * Returns permission id on success
  */
  public function getPermName(int $perm_id)
  {
    $perm_id = CIRMS::sanitize($perm_id);
    $this->perm_name = $this->selectSQL("iems_ac_permissions","perm_name","perm_id='{$perm_id}'");
    return $this->perm_name;
  }

  /**
  * Add single role name
  * Accepts value as a string
  * Returns new role id on success
  */
  public function addRole($name,$desc,$owner)
  {
    $name = CIRMS::sanitize($name);
    $desc = CIRMS::sanitize($desc);
    if (!empty($owner) && mb_strtolower($owner)!='system') {
      $role_owner = CIRMS::sanitize($owner);
    } else {
      $role_owner = "Custom";
    }
    $table = "iems_ac_roles";
    $values = "'{$name}','{$desc}','{$role_owner}'";
    $columns = "role_name,role_desc,role_owner";
    if ($this->insertSQL($table,$values,$columns)) {
      galaxy_log_activities("Add Role","Added <b>{$name}</b> role.");
      return $this->getlastinsertID();
    } else {
      return FALSE;
    }
  }

  /**
  * Update role details
  * Accepts value as int, string
  * It returns true on success
  */
  public function updateRole(int $role_id,$name,$desc,$owner="")
  {
    if (isset($owner)) {
      $owner = CIRMS::sanitize($owner);
    } else {
      $owner = $this->selectSQL($table,"role_owner","role_id='{$role_id}'");
    }
    $desc = CIRMS::sanitize($desc);
    $role_id = CIRMS::sanitize($role_id);
    $name = CIRMS::sanitize($name);
    $what_to_set = "role_name='{$name}',role_desc='{$desc}',role_owner='{$owner}'";
    $where = "role_id = {$role_id}";
    $table = "iems_ac_roles";
    $former = $this->selectSQL($table,"role_name","role_id='{$role_id}' AND role_owner!='System'");
    $this->updateSQL($table,$what_to_set,$where);
    if ($this->affectedrowsSQL() > 0) {
      galaxy_log_activities("Update Roles","Updated {$former} to {$name}.");
      return TRUE;
    } else {
      iems_log_errors("Error encoutered while updating {$name} into {$table} considering ({$where})",get_class()." class (".__FUNCTION__ .")",$this->dbconnect->error);
      return FALSE;
    }
  }

  /**
  * Delete role details
  * Accepts value as an array
  * Returns true on success
  */
  public function deleteRole(array $name)
  {
    foreach ($name as $role_name) {
      $role_name = CIRMS::sanitize($role_name);
      $table = "iems_ac_roles";
      $table2 = "iems_ac_role_permissions";
      $table3 = "iems_ac_user_privileges";
      $role_id = $this->selectSQL($table,"role_id","role_name='{$role_name}' AND role_owner!='System'");
      $description = $this->selectSQL($table,"role_desc","role_id='{$role_id}'");
      $where = "role_id = {$role_id}";
      $this->deleteSQL($table2,$where);
      $this->deleteSQL($table3,$where);
      $this->deleteSQL($table,$where);
      if ($this->affectedrowsSQL() > 0) {
        galaxy_log_activities("Delete Permission","Deleted {$role_id} ({$role_name} - {$description}).");
        return TRUE;
      } else {
        iems_log_errors("Error encoutered while deleting {$role_id} ({$role_name} - {$description}) from {$table} considering ({$where})",get_class()." class (".__FUNCTION__ .")",$this->dbconnect->error);
        return FALSE;
      }
    }
  }

  /**
  * Get role id
  * Accepts value as a string
  * Returns role id on success
  */
  public function getRoleID($role_name)
  {
    $role_name = CIRMS::sanitize($role_name);
    $this->role_id = $this->selectSQL("iems_ac_roles","role_id","role_name='{$role_name}'");
    return $this->role_id;
  }

  /**
  * Get permission name
  * Accepts value as an integer
  * Returns permission id on success
  */
  public function getRoleName(int $role_id)
  {
    $role_id = CIRMS::sanitize($role_id);
    $this->role_name = $this->selectSQL("iems_ac_roles","role_name","role_id='{$role_id}'");
    return $this->role_name;
  }

  /**
  * Add single role id with multiple permissions
  * Accepts value as integer and array
  * Returns new role id on success as array
  */
  public function addRolePerm(int $role_id, array $perm_id)
  {
    $role_id = CIRMS::sanitize($role_id);
    $namerole = $this->getRoleName($role_id);
    foreach ($perm_id as $permid) {
      $permid = CIRMS::sanitize($permid);
      $nameperm = $this->getPermName($permid);
      $table = "iems_ac_role_permissions";
      $values = "'{$role_id}','{$permid}'";
      $columns = "role_id,perm_id";
      if ($this->insertSQL($table,$values,$columns)) {
        galaxy_log_activities("Add Role Permission","Added <b>{$nameperm}</b> permission to <b>{$namerole}</b> role.");
        $roleperm[] = $this->getlastinsertID();
      } else {
        iems_log_errors("Error encoutered while adding <b>{$nameperm}</b> permission to <b>{$namerole}</b> role into {$table} considering ({$where})",get_class()." class (".__FUNCTION__ .")",$this->dbconnect->error);
      }
    }
    if (is_array($roleperm)) {
      return $roleperm;
    } else {
      return false;
    }
  }

  /**
  * Delete role permission details. Not implemented yet
  * @param array $rolepermid Supply the
  * @return true|false
  */
  public function deleteRolePerm(array $rolepermid)
  {
    $x = 1;
    foreach ($name as $role_name) {
      $role_name = CIRMS::sanitize($role_name);
      $table = "iems_ac_roles";
      $table2 = "iems_ac_role_permissions";
      $table3 = "iems_ac_user_privileges";
      $role_id = $this->selectSQL($table,"role_id","role_name='{$role_name}' AND role_owner!='System'");
      $description = $this->selectSQL($table,"role_desc","role_id='{$role_id}'");
      $where = "role_id = {$role_id}";
      $this->deleteSQL($table2,$where);
      $this->deleteSQL($table3,$where);
      $this->deleteSQL($table,$where);
      if ($this->affectedrowsSQL() > 0) {
        galaxy_log_activities("Delete Permission","Deleted {$role_id} ({$role_name} - {$description}).");
        $count[] = $x;
      } else {
        iems_log_errors("Error encoutered while deleting {$role_id} ({$role_name} - {$description}) from {$table} considering ({$where})",get_class()." class (".__FUNCTION__ .")",$this->dbconnect->error);
      }
      $x++;
    }
    if (is_array($count)) {
      return $count;
    } else {
      return false;
    }
  }

  /**
  * Add a specific user to a role
  * @param string $user_id Supply the id of the user
  * @param string $role_id Supply the id of the role
  * @return true|false
  */
  public function addUserRole($user_id,$role_id)
  {
    $user_id = CIRMS::sanitize($user_id);
    $role_id = CIRMS::sanitize($role_id);
    $namerole = $this->getRoleName($role_id);
    $fullname = iems_getUser('fullname',$user_id);
    $table = "iems_ac_user_privileges";
    $values = "'{$role_id}','{$user_id}'";
    $columns = "role_id,user_id";
    if ($this->insertSQL($table,$values,$columns)) {
      galaxy_log_activities("Add User Role","Added <b>{$fullname} ({$user_id})</b> to <b>{$namerole}</b> role.");
      return true;
    } else {
      iems_log_errors("Error encoutered while adding <b>{$nameperm}</b> permission to <b>{$namerole}</b> role into {$table} considering ({$where})",get_class()." class (".__FUNCTION__ .")",$this->dbconnect->error);
      return false;
    }
  }

  /**
  * Update user role details
  * Accepts value as a string
  * It returns true on success
  */
  public function updateUserRole($user_id, int $role)
  {
    $role_id = CIRMS::sanitize($role_id);
    $name = CIRMS::sanitize($name);
    $what_to_set = "role_name='$name'";
    $where = "role_id = {$role_id}";
    $table = "iems_ac_roles";
    $former = $this->selectSQL($table,"role_name","role_id='{$role_id}'");
    $this->updateSQL($table,$what_to_set,$where);
    if ($this->affectedrowsSQL() > 0) {
      galaxy_log_activities("Update Roles","Updated {$former} to {$name}.");
      return TRUE;
    } else {
      iems_log_errors("Error encoutered while updating {$name} into {$table} considering ({$where})",get_class()." class (".__FUNCTION__ .")",$this->dbconnect->error);
      return FALSE;
    }
  }

  /**
  * Delete a specific user's role
  * @param string $user_id Supply the id of the user
  * @param string $role_id Supply the id of the role
  * @return true|false
  */
  public function deleteUserRole($user_id,$role_id)
  {
    // code...
  }

  /**
  * Verify if the user has a permission
  * Accepts value as a string
  * Returns true or false
  */
  public function checkPerm($user_id,$perm_name)
  {
    $user_id = CIRMS::sanitize($user_id);
    $perm_name = CIRMS::sanitize($perm_name);
    $perm_id = $this->selectSQL("iems_ac_permissions","perm_id","perm_name='{$perm_name}'");
    $sql = "SELECT `iems_ac_permissions`.`perm_name` FROM iems_ac_permissions
            INNER JOIN iems_ac_role_permissions ON `iems_ac_role_permissions`.`perm_id` = `iems_ac_permissions`.`perm_id`
            INNER JOIN iems_ac_user_privileges ON `iems_ac_user_privileges`.`role_id` = `iems_ac_role_permissions`.`role_id`
            WHERE `iems_ac_user_privileges`.`user_id`='$user_id' AND `iems_ac_permissions`.`perm_id`='$perm_id'";
    $check = $this->dbconnect->query($sql);
    $row = $check->fetch_array();
    $count = $check->num_rows;
    if ($count > 0) {
      return true;
    } else {
      // Allow super admin access to entire functionalities
      if ($this->isSuperAdmin()) {
        return true;
      } else {
        return false;
      }
    }
  }

  /**
  * Verify if the user has a permission
  * Accepts value as a string
  * Returns true or ends the script if false
  * Should be used cautiously
  */
  public function enforce($user_id,$perm_name)
  {
    $user_id = CIRMS::sanitize($user_id);
    $perm_name = CIRMS::sanitize($perm_name);
    $perm_id = $this->selectSQL("iems_ac_permissions","perm_id","perm_name='{$perm_name}'");
    $sql = "SELECT `iems_ac_permissions`.`perm_name` FROM iems_ac_permissions
            INNER JOIN iems_ac_role_permissions ON `iems_ac_role_permissions`.`perm_id` = `iems_ac_permissions`.`perm_id`
            INNER JOIN iems_ac_user_privileges ON `iems_ac_user_privileges`.`role_id` = `iems_ac_role_permissions`.`role_id`
            WHERE `iems_ac_user_privileges`.`user_id`='$user_id' AND `iems_ac_permissions`.`perm_id`='$perm_id'";
    $check = $this->dbconnect->query($sql);
    $row = $check->fetch_array();
    $count = $check->num_rows;
    if ($count > 0) {
      return true;
    } else {
      // Allow super admin access to entire functionalities
      if ($this->isSuperAdmin()) {
        return true;
      } else {
        include _iEMS_INSTALL_LOCATION.'/no-access.php';
      }
      exit;
    }
  }


  /**
  * Verify if a user has any role
  * @param Accepts value as a string
  * Returns null shows no access page
  */
  public function hasAnyRole($user_id,$enforce=true,$inc=_iEMS_INSTALL_LOCATION.'no-access.php')
  {
    $user_id = CIRMS::sanitize($user_id);
    $has_access = $this->selectSQL("iems_ac_user_privileges","user_id","user_id='{$user_id}'");
    if ($enforce==false) {
      if ($has_access==false) {
        if ($this->isSuperAdmin()) {
          return true;
        } else {
          return false;
        }
      } else {
        return true;
      }
    } else {
      if ($has_access==false) {
        if ($this->isSuperAdmin()) {
          return true;
        } else {
          include $inc;
          exit;
        }
      }
    }
  }


  /**
  * Verify if the user is among a role
  * Accepts value as a string
  * Returns true or false
  */
  public function hasRole($user_id,$role_id,$checkSuperAdmin=true)
  {
    $user_id = CIRMS::sanitize($user_id);
    $role_id = CIRMS::sanitize($role_id);
    $confirm_role = $this->selectSQL("iems_ac_user_privileges","user_id","role_id='{$role_id}' AND user_id='$user_id'");
    if ($confirm_role && $confirm_role===$user_id) {
      return true;
    } else {
      if (!$checkSuperAdmin) {
        return false;
      } else {
        // Allow super admin access to entire functionalities
        if ($this->isSuperAdmin()) {
          return true;
        } else {
          return false;
        }
      }
    }
  }


  /**
  * Verify if the user is a super admin
  * Accepts value as a string
  * Returns true or false
  */
  public function isSuperAdmin($user_id=USER_ID)
  {
    $user_id = \CIRMS::sanitize($user_id);
    $role_id = 1;
    $confirm_role = $this->selectSQL("iems_ac_user_privileges","user_id","role_id='{$role_id}' AND user_id='$user_id'");
    if ($confirm_role && $confirm_role===$user_id) {
      return true;
    } else {
      return false;
    }
  }

  /**
  * Insert data into a specified table
  */
  private function insertSQL($table,$values,$columns)
  {
    $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$values})";
    if ($this->dbconnect->query($sql)) {
      return TRUE;
    } else {
      iems_log_errors("Inserting {$values} into {$table} against ({$columns})",get_class()." class (".__FUNCTION__ .")",$this->dbconnect->error);
      return FALSE;
    }
  }

  private function updateSQL($table,$what,$where,$conditions='')
  {
    $sql = "UPDATE {$table} SET {$what} WHERE {$where} {$conditions}";
    if ($this->dbconnect->query($sql)) {
      return TRUE;
    } else {
      iems_log_errors("Updating {$table} with {$what}. Condition: {$where} .::. {$conditions}",get_class()." class (".__FUNCTION__ .")",$this->dbconnect->error);
      return FALSE;
    }
  }

  /**
  * Delete data from a given table
  */
  private function deleteSQL($table,$where='')
  {
    if (isset($where)) {
      $sql = "DELETE FROM {$table} WHERE {$where}";
    } else {
      $sql = "DELETE FROM {$table}";
    }
    if ($this->dbconnect->query($sql)) {
      return TRUE;
    } else {
      iems_log_errors("Deleting from {$table}. Condition: {$where}",get_class()." class (".__FUNCTION__ .")",$this->dbconnect->error);
      return FALSE;
    }
  }

  private function selectSQL($table,$what,$where='',$conditions='')
  {
    $table = CIRMS::sanitize($table);
    $what = CIRMS::sanitize($what);
    if (isset($where)) {
      $sql = "SELECT {$what} FROM {$table} WHERE {$where} {$conditions}";
    } else {
      $sql = "SELECT {$what} FROM {$table} {$conditions}";
    }
    $query = $this->dbconnect->query($sql) or iems_log_errors("Selecting {$what} from {$table}. Condition: {$where} .::. {$conditions}",get_class()." class (".__FUNCTION__ .")",$this->dbconnect->error);
    $row = $query->fetch_array();
    if ($query->num_rows > 0) {
      return $row[$what];
    } else {
      return FALSE;
    }
  }

  private function getlastinsertID()
  {
    return $this->dbconnect->insert_id;
  }

  /**
  * Return affected rows
  */
  private function affectedrowsSQL()
  {
    return $this->dbconnect->affected_rows;
  }


  /**
  * Close database connection
  */
  private function closeDB()
  {
    return $this->dbconnect->db_close();
  }

}
