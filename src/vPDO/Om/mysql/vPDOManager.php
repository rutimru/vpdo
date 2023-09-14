<?php
/**
 * This file is part of the vPDO package.
 *
 * Copyright (c) Jason Coward <jason@opengeek.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vPDO\Om\mysql;

use vPDO\vPDO;

/**
 * Provides MySQL data source management for an vPDO instance.
 *
 * These are utility functions that only need to be loaded under special
 * circumstances, such as creating tables, adding indexes, altering table
 * structures, etc.  vPDOManager class implementations are specific to a
 * database driver and this instance is implemented for MySQL.
 *
 * @package vPDO
 * @subpackage om.mysql
 */
class vPDOManager extends \vPDO\Om\vPDOManager {
    public function createSourceContainer($dsnArray = null, $username= null, $password= null, $containerOptions= array ()) {
        $created= false;
        if ($this->vpdo->getConnection(array(vPDO::OPT_CONN_MUTABLE => true))) {
            if ($dsnArray === null) $dsnArray = vPDO::parseDSN($this->vpdo->getOption('dsn'));
            if ($username === null) $username = $this->vpdo->getOption('username', null, '');
            if ($password === null) $password = $this->vpdo->getOption('password', null, '');
            if (is_array($dsnArray) && is_string($username) && is_string($password)) {
                $sql= 'CREATE DATABASE `' . $dsnArray['dbname'] . '`';
                $charset = $this->vpdo->getOption('charset', $containerOptions);
                $collation = $this->vpdo->getOption('collation', $containerOptions);
                if (!empty($charset)) {
                    $sql .= ' CHARACTER SET ' . $charset;
                }
                if (!empty($collation)) {
                    $sql.= ' COLLATE ' . $collation;
                }
                try {
                    $pdo = new \PDO("mysql:host={$dsnArray['host']}", $username, $password, array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION));
                    $result = $pdo->exec($sql);
                    if ($result !== false) {
                        $created = true;
                    } else {
                        $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not create source container:\n{$sql}\nresult = " . var_export($result, true));
                    }
                } catch (\PDOException $pe) {
                    $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not connect to database server: " . $pe->getMessage());
                } catch (\Exception $e) {
                    $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not create source container: " . $e->getMessage());
                }
            }
        } else {
            $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $created;
    }

    public function removeSourceContainer($dsnArray = null, $username= null, $password= null) {
        $removed= false;
        if ($this->vpdo->getConnection(array(vPDO::OPT_CONN_MUTABLE => true))) {
            if ($dsnArray === null) $dsnArray = vPDO::parseDSN($this->vpdo->getOption('dsn'));
            if ($username === null) $username = $this->vpdo->getOption('username', null, '');
            if ($password === null) $password = $this->vpdo->getOption('password', null, '');
            if (is_array($dsnArray) && is_string($username) && is_string($password)) {
                $sql= 'DROP DATABASE IF EXISTS ' . $this->vpdo->escape($dsnArray['dbname']);
                try {
                    $pdo = new \PDO("mysql:host={$dsnArray['host']}", $username, $password, array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION));
                    $result = $pdo->exec($sql);
                    if ($result !== false) {
                        $removed = true;
                    } else {
                        $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not remove source container:\n{$sql}\nresult = " . var_export($result, true));
                    }
                } catch (\PDOException $pe) {
                    $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not connect to database server: " . $pe->getMessage());
                } catch (\Exception $e) {
                    $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not remove source container: " . $e->getMessage());
                }
            }
        } else {
            $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $removed;
    }

    public function removeObjectContainer($className) {
        $removed= false;
        if ($this->vpdo->getConnection(array(vPDO::OPT_CONN_MUTABLE => true))) {
            $instance= $this->vpdo->newObject($className);
            if ($instance) {
                $sql= 'DROP TABLE ' . $this->vpdo->getTableName($className);
                $removed= $this->vpdo->exec($sql);
                if ($removed === false && $this->vpdo->errorCode() !== '' && $this->vpdo->errorCode() !== \PDO::ERR_NONE) {
                    $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, 'Could not drop table ' . $className . "\nSQL: {$sql}\nERROR: " . print_r($this->vpdo->pdo->errorInfo(), true));
                } else {
                    $removed= true;
                    $this->vpdo->log(vPDO::LOG_LEVEL_INFO, 'Dropped table' . $className . "\nSQL: {$sql}\n");
                }
            }
        } else {
            $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $removed;
    }

    public function createObjectContainer($className) {
        $created= false;
        if ($this->vpdo->getConnection(array(vPDO::OPT_CONN_MUTABLE => true))) {
            $instance= $this->vpdo->newObject($className);
            if ($instance) {
                $tableName= $this->vpdo->getTableName($className);
                $existsStmt = $this->vpdo->query("SELECT COUNT(*) FROM {$tableName}");
                if ($existsStmt && $existsStmt->fetchAll()) {
                    return true;
                }
                $modelVersion= $this->vpdo->getModelVersion($className);
                $tableMeta= $this->vpdo->getTableMeta($className);
                $tableType= isset($tableMeta['engine']) ? $tableMeta['engine'] : 'InnoDB';
                $tableType= $this->vpdo->getOption(vPDO::OPT_OVERRIDE_TABLE_TYPE, null, $tableType);
                $legacyIndexes= version_compare($modelVersion, '1.1', '<');
                $fulltextIndexes= array ();
                $uniqueIndexes= array ();
                $stdIndexes= array ();
                $sql= 'CREATE TABLE ' . $tableName . ' (';
                $fieldMeta = $this->vpdo->getFieldMeta($className, true);
                $columns = array();
                foreach ($fieldMeta as $key => $meta) {
                    $columns[] = $this->getColumnDef($className, $key, $meta);
                    /* Legacy index support for pre-2.0.0-rc3 models */
                    if ($legacyIndexes && isset ($meta['index']) && $meta['index'] !== 'pk') {
                        if ($meta['index'] === 'fulltext') {
                            if (isset ($meta['indexgrp'])) {
                                $fulltextIndexes[$meta['indexgrp']][]= $key;
                            } else {
                                $fulltextIndexes[$key]= $key;
                            }
                        }
                        elseif ($meta['index'] === 'unique') {
                            if (isset ($meta['indexgrp'])) {
                                $uniqueIndexes[$meta['indexgrp']][]= $key;
                            } else {
                                $uniqueIndexes[$key]= $key;
                            }
                        }
                        elseif ($meta['index'] === 'fk') {
                            if (isset ($meta['indexgrp'])) {
                                $stdIndexes[$meta['indexgrp']][]= $key;
                            } else {
                                $stdIndexes[$key]= $key;
                            }
                        } else {
                            if (isset ($meta['indexgrp'])) {
                                $stdIndexes[$meta['indexgrp']][]= $key;
                            } else {
                                $stdIndexes[$key]= $key;
                            }
                        }
                    }
                }
                $sql .= implode(', ', $columns);
                if (!$legacyIndexes) {
                    $indexes = $this->vpdo->getIndexMeta($className);
                    $tableConstraints = array();
                    if (!empty ($indexes)) {
                        foreach ($indexes as $indexkey => $indexdef) {
                            $tableConstraints[] = $this->getIndexDef($className, $indexkey, $indexdef);
                        }
                    }
                } else {
                    /* Legacy index support for schema model versions 1.0 */
                    $pk= $this->vpdo->getPK($className);
                    if (is_array($pk)) {
                        $pkarray= array ();
                        foreach ($pk as $k) {
                            $pkarray[]= $this->vpdo->escape($k);
                        }
                        $pk= implode(',', $pkarray);
                    }
                    elseif ($pk) {
                        $pk= $this->vpdo->escape($pk);
                    }
                    if ($pk) {
                        $tableConstraints[]= "PRIMARY KEY ({$pk})";
                    }
                    if (!empty ($stdIndexes)) {
                        foreach ($stdIndexes as $indexkey => $index) {
                            if (is_array($index)) {
                                $indexset= array ();
                                foreach ($index as $indexmember) {
                                    $indexset[]= $this->vpdo->escape($indexmember);
                                }
                                $indexset= implode(',', $indexset);
                            } else {
                                $indexset= $this->vpdo->escape($indexkey);
                            }
                            $tableConstraints[]= "INDEX {$this->vpdo->escape($indexkey)} ({$indexset})";
                        }
                    }
                    if (!empty ($uniqueIndexes)) {
                        foreach ($uniqueIndexes as $indexkey => $index) {
                            if (is_array($index)) {
                                $indexset= array ();
                                foreach ($index as $indexmember) {
                                    $indexset[]= $this->vpdo->escape($indexmember);
                                }
                                $indexset= implode(',', $indexset);
                            } else {
                                $indexset= $this->vpdo->escape($indexkey);
                            }
                            $tableConstraints[]= "UNIQUE INDEX {$this->vpdo->escape($indexkey)} ({$indexset})";
                        }
                    }
                    if (!empty ($fulltextIndexes)) {
                        foreach ($fulltextIndexes as $indexkey => $index) {
                            if (is_array($index)) {
                                $indexset= array ();
                                foreach ($index as $indexmember) {
                                    $indexset[]= $this->vpdo->escape($indexmember);
                                }
                                $indexset= implode(',', $indexset);
                            } else {
                                $indexset= $this->vpdo->escape($indexkey);
                            }
                            $tableConstraints[]= "FULLTEXT INDEX {$this->vpdo->escape($indexkey)} ({$indexset})";
                        }
                    }
                }
                if (!empty($tableConstraints)) {
                    $sql .= ', ' . implode(', ', $tableConstraints);
                }
                $sql .= ")";
                if (!empty($tableType)) {
                    $sql .= " ENGINE={$tableType}";
                }
                $created= $this->vpdo->exec($sql);
                if ($created === false && $this->vpdo->errorCode() !== '' && $this->vpdo->errorCode() !== \PDO::ERR_NONE) {
                    $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, 'Could not create table ' . $tableName . "\nSQL: {$sql}\nERROR: " . print_r($this->vpdo->errorInfo(), true));
                } else {
                    $created= true;
                    $this->vpdo->log(vPDO::LOG_LEVEL_INFO, 'Created table ' . $tableName . "\nSQL: {$sql}\n");
                }
            }
        } else {
            $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $created;
    }

    public function alterObjectContainer($className, array $options = array()) {
        if ($this->vpdo->getConnection(array(vPDO::OPT_CONN_MUTABLE => true))) {
            // TODO: Implement alterObjectContainer() method.
        } else {
            $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
    }

    public function addConstraint($class, $name, array $options = array()) {
        if ($this->vpdo->getConnection(array(vPDO::OPT_CONN_MUTABLE => true))) {
            // TODO: Implement addConstraint() method.
        } else {
            $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
    }

    public function addField($class, $name, array $options = array()) {
        $result = false;
        if ($this->vpdo->getConnection(array(vPDO::OPT_CONN_MUTABLE => true))) {
            $className = $this->vpdo->loadClass($class);
            if ($className) {
                $meta = $this->vpdo->getFieldMeta($className, true);
                if (is_array($meta) && array_key_exists($name, $meta)) {
                    $colDef = $this->getColumnDef($className, $name, $meta[$name]);
                    if (!empty($colDef)) {
                        $sql = "ALTER TABLE {$this->vpdo->getTableName($className)} ADD COLUMN {$colDef}";
                        if (isset($options['first']) && !empty($options['first'])) {
                            $sql .= " FIRST";
                        } elseif (isset($options['after']) && array_key_exists($options['after'], $meta)) {
                            $sql .= " AFTER {$this->vpdo->escape($options['after'])}";
                        }
                        if ($this->vpdo->exec($sql) !== false) {
                            $result = true;
                        } else {
                            $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Error adding field {$class}->{$name}: " . print_r($this->vpdo->errorInfo(), true), '', __METHOD__, __FILE__, __LINE__);
                        }
                    } else {
                        $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Error adding field {$class}->{$name}: Could not get column definition");
                    }
                } else {
                    $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Error adding field {$class}->{$name}: No metadata defined");
                }
            }
        } else {
            $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $result;
    }

    public function addIndex($class, $name, array $options = array()) {
        $result = false;
        if ($this->vpdo->getConnection(array(vPDO::OPT_CONN_MUTABLE => true))) {
            $className = $this->vpdo->loadClass($class);
            if ($className) {
                $meta = $this->vpdo->getIndexMeta($className);
                if (is_array($meta) && array_key_exists($name, $meta)) {
                    $idxDef = $this->getIndexDef($className, $name, $meta[$name]);
                    if (!empty($idxDef)) {
                        $sql = "ALTER TABLE {$this->vpdo->getTableName($className)} ADD {$idxDef}";
                        if ($this->vpdo->exec($sql) !== false) {
                            $result = true;
                        } else {
                            $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Error adding index {$name} to {$class}: " . print_r($this->vpdo->errorInfo(), true), '', __METHOD__, __FILE__, __LINE__);
                        }
                    } else {
                        $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Error adding index {$name} to {$class}: Could not get index definition");
                    }
                } else {
                    $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Error adding index {$name} to {$class}: No metadata defined");
                }
            }
        } else {
            $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $result;
    }

    public function alterField($class, $name, array $options = array()) {
        $result = false;
        if ($this->vpdo->getConnection(array(vPDO::OPT_CONN_MUTABLE => true))) {
            $className = $this->vpdo->loadClass($class);
            if ($className) {
                $meta = $this->vpdo->getFieldMeta($className, true);
                if (is_array($meta) && array_key_exists($name, $meta)) {
                    $colDef = $this->getColumnDef($className, $name, $meta[$name]);
                    if (!empty($colDef)) {
                        $sql = "ALTER TABLE {$this->vpdo->getTableName($className)} MODIFY COLUMN {$colDef}";
                        if (isset($options['first']) && !empty($options['first'])) {
                            $sql .= " FIRST";
                        } elseif (isset($options['after']) && array_key_exists($options['after'], $meta)) {
                            $sql .= " AFTER {$this->vpdo->escape($options['after'])}";
                        }
                        if ($this->vpdo->exec($sql) !== false) {
                            $result = true;
                        } else {
                            $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Error altering field {$class}->{$name}: " . print_r($this->vpdo->errorInfo(), true), '', __METHOD__, __FILE__, __LINE__);
                        }
                    } else {
                        $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Error altering field {$class}->{$name}: Could not get column definition");
                    }
                } else {
                    $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Error altering field {$class}->{$name}: No metadata defined");
                }
            }
        } else {
            $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $result;
    }

    public function removeConstraint($class, $name, array $options = array()) {
        if ($this->vpdo->getConnection(array(vPDO::OPT_CONN_MUTABLE => true))) {
            // TODO: Implement removeConstraint() method.
        } else {
            $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
    }

    public function removeField($class, $name, array $options = array()) {
        $result = false;
        if ($this->vpdo->getConnection(array(vPDO::OPT_CONN_MUTABLE => true))) {
            $className = $this->vpdo->loadClass($class);
            if ($className) {
                $sql = "ALTER TABLE {$this->vpdo->getTableName($className)} DROP COLUMN {$this->vpdo->escape($name)}";
                if ($this->vpdo->exec($sql) !== false) {
                    $result = true;
                } else {
                    $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Error removing field {$class}->{$name}: " . print_r($this->vpdo->errorInfo(), true), '', __METHOD__, __FILE__, __LINE__);
                }
            }
        } else {
            $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $result;
    }

    public function removeIndex($class, $name, array $options = array()) {
        $result = false;
        if ($this->vpdo->getConnection(array(vPDO::OPT_CONN_MUTABLE => true))) {
            $className = $this->vpdo->loadClass($class);
            if ($className) {
                $sql = "ALTER TABLE {$this->vpdo->getTableName($className)} DROP INDEX {$this->vpdo->escape($name)}";
                if ($this->vpdo->exec($sql) !== false) {
                    $result = true;
                } else {
                    $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Error removing index {$name} from {$class}: " . print_r($this->vpdo->errorInfo(), true), '', __METHOD__, __FILE__, __LINE__);
                }
            }
        } else {
            $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $result;
    }

    protected function getColumnDef($class, $name, $meta, array $options = array()) {
        $pk= $this->vpdo->getPK($class);
        $pktype= $this->vpdo->getPKType($class);
        $dbtype= strtoupper($meta['dbtype']);
        $lobs= array ('TEXT', 'BLOB');
        $lobsPattern= '/(' . implode('|', $lobs) . ')/';
        $datetimeStrings= array('timestamp', 'datetime');
        $precision= isset ($meta['precision']) ? '(' . $meta['precision'] . ')' : '';
        $notNull= !isset ($meta['null']) ? false : ($meta['null'] === 'false' || empty($meta['null']));
        $null= $notNull ? ' NOT NULL' : ' NULL';
        $extra= '';
        if (isset($meta['index']) && $meta['index'] == 'pk' && !is_array($pk) && $pktype == 'integer' && isset ($meta['generated']) && $meta['generated'] == 'native') {
            $extra= ' AUTO_INCREMENT';
        }
        if (empty ($extra) && isset ($meta['extra'])) {
            $extra= ' ' . $meta['extra'];
        }
        $default= '';
        if (isset ($meta['default']) && !preg_match($lobsPattern, $dbtype)) {
            $defaultVal= $meta['default'];
            if (($defaultVal === null || strtoupper($defaultVal) === 'NULL') || (in_array($this->vpdo->driver->getPhpType($dbtype), $datetimeStrings) && $defaultVal === 'CURRENT_TIMESTAMP')) {
                $default= ' DEFAULT ' . $defaultVal;
            } else {
                $default= ' DEFAULT ' . $this->vpdo->quote($defaultVal, \PDO::PARAM_STR);
            }
        }
        $attributes= (isset ($meta['attributes'])) ? ' ' . $meta['attributes'] : '';
        if (strpos(strtolower($attributes), 'unsigned') !== false) {
            $result = $this->vpdo->escape($name) . ' ' . $dbtype . $precision . $attributes . $null . $default . $extra;
        } else {
            $result = $this->vpdo->escape($name) . ' ' . $dbtype . $precision . $null . $default . $attributes . $extra;
        }
        return $result;
    }

    protected function getIndexDef($class, $name, $meta, array $options = array()) {
        $result = '';
        if (isset($meta['type']) && $meta['type'] == 'FULLTEXT') {
            $indexType = 'FULLTEXT';
        } else if ( ! empty($meta['primary'])) {
            $indexType = 'PRIMARY KEY';
        } else if ( ! empty($meta['unique'])) {
            $indexType = 'UNIQUE KEY';
        } else {
            $indexType = 'INDEX';
        }
        $index = $meta['columns'];
        if (is_array($index)) {
            $indexset= array ();
            foreach ($index as $indexmember => $indexmemberdetails) {
                $indexMemberDetails = $this->vpdo->escape($indexmember);
                if (isset($indexmemberdetails['length']) && !empty($indexmemberdetails['length'])) {
                    $indexMemberDetails .= " ({$indexmemberdetails['length']})";
                }
                $indexset[]= $indexMemberDetails;
            }
            $indexset= implode(',', $indexset);
            if (!empty($indexset)) {
                switch ($indexType) {
                    case 'PRIMARY KEY':
                        $result= "{$indexType} ({$indexset})";
                        break;
                    default:
                        $result= "{$indexType} {$this->vpdo->escape($name)} ({$indexset})";
                        break;
                }
            }
        }
        return $result;
    }
}
