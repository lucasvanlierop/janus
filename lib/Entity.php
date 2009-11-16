<?php
/**
 * An entity
 *
 * PHP version 5
 *
 * JANUS is free software: you can redistribute it and/or modify it under the
 * terms of the GNU Lesser General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option)
 * any later version.
 *
 * JANUS is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with JANUS. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category   SimpleSAMLphp
 * @package    JANUS
 * @subpackage Core
 * @author     Jacob Christiansen <jach@wayf.dk>
 * @copyright  2009 Jacob Christiansen
 * @license    http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 * @version    SVN: $Id$
 * @link       http://code.google.com/p/janus-ssp/
 * @since      File available since Release 1.0.0
 */
/**
 * An entity
 *
 * Contains basic functionality used for entity generation and management.
 *
 * @category   SimpleSAMLphp
 * @package    JANUS
 * @subpackage Core
 * @author     Jacob Christiansen <jach@wayf.dk>
 * @copyright  2009 Jacob Christiansen
 * @license    http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 * @version    SVN: $Id$
 * @link       http://code.google.com/p/janus-ssp/
 * @since      Class available since Release 1.0.0
 * @todo       Remove default values on _system, _state, _type in final release.
 */
class sspmod_janus_Entity extends sspmod_janus_Database
{
    /*
     * Internal id for referencing the entity
     * @var int
     */
    private $_eid;

    /*
     * Pointer to revision id of parent entity
     * @var int
     */
    private $_parent;

    /*
     * Revision note for entity
     * @var int
     */
    private $_revisionnote;

    /**
     * Entity id
     * @var string
     */
    private $_entityid;

    /**
     * Revision id.
     * @var int Revision number.
     */
    private $_revisionid;

    /**
     * Curent system
     * @var string
     */
    private $_system = 'test';

    /**
     * Current state
     * @var string
     */
    private $_state = 'accepted';

    /**
     * Current workflow
     * @var string
     */
    private $_workflow;

    /**
     * Entity type
     * @var string
     */
    private $_type = 'idp';

    /**
     * Expiration date of current entity.
     * @var DateTime
     */
    private $_expiration;

    /**
     * URL of the entities metadata
     * @var string
     */
    private $_metadataurl;

    /**
     * Entity allowes all other entities
     * @var string Will possible change in the future
     */
    private $_allowedall = 'yes';

    /**
     * Indicates whether that entity data has been modified
     * @var bool
     */
    private $_modified = false;

    /**
     * Create new entity
     *
     * Will instanciate a new entity given correct configuration. If parsed the
     * new flag, a new eid will be generated.
     *
     * @param array $config Configuration for the database
     * @param bool  $new    Is entity new, default false
     */
    public function __construct($config, $new = false)
    {
        // To start with only the store config is parsed til user
        parent::__construct($config);

        // If entity is new, get new eid
        if ($new) {
            $this->_getNewEid();
        }
    }

    /**
     * Save entity data
     *
     * Method for saving the entity data to the database. If the entity data have
     * not been modified since last load, the method returns true without saving.
     * Method return false if an error has occured otherwise it will return the
     * PDOstatement executed.
     *
     * @return PDOStatement|bool Returns the statement on success.
     */
    public function save()
    {
        if (!$this->_modified) {
            return true;
        }

        if (!empty($this->_entityid) && !empty($this->_eid)) {
            // Get next revisionid
            $st = $this->execute(
                'SELECT MAX(`revisionid`) AS maxrevisionid 
                FROM '. self::$prefix .'entity 
                WHERE `eid` = ?;',
                array($this->_eid)
            );

            if ($st === false) {
                return false;
            }
            $row = $st->fetchAll(PDO::FETCH_ASSOC);

            if ($row[0]['maxrevisionid'] === null) {
                $new_revisionid = 0;
            } else {
                $new_revisionid = $row[0]['maxrevisionid'] + 1;
            }

            $st = $this->execute(
                'INSERT INTO '. self::$prefix .'entity 
                (`eid`, `entityid`, `revisionid`, `state`, `type`, 
                `expiration`, `metadataurl`, `allowedall`, `created`, `ip`, `parent`, `revisionnote`) 
                VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);',
                array(
                    $this->_eid,
                    $this->_entityid,
                    $new_revisionid,
                    $this->_workflow,
                    $this->_type,
                    $this->_expiration,
                    $this->_metadataurl,
                    $this->_allowedall,
                    date('c'),
                    $_SERVER['REMOTE_ADDR'],
                    $this->_parent,
                    $this->_revisionnote,
                )
            );

            if ($st === false) {
                return false;
            }

            $this->_revisionid = $new_revisionid;

            $this->_modified = false;
        } else {
            return false;
        }
        return $st;
    }

    /**
     * Return the next free eid
     *
     * @return bool True on success
     */
    private function _getNewEid()
    {
        $st = $this->execute(
            'SELECT MAX(`eid`) AS `maxeid` 
            FROM '. self::$prefix .'entity;'
        );

        $row = $st->fetchAll(PDO::FETCH_ASSOC);

        if ($row[0]['maxeid'] === null) {
            // First entity in system
            $this->_eid = 1;
        } else {
            $this->_eid = $row[0]['maxeid'] + 1;
        }
        return true;
    }

    /**
     * Get the newest revision id of entity
     *
     * @return bool True on success and false on error
     */
    private function _newestRevision()
    {
        $st = $this->execute(
            'SELECT MAX(`revisionid`) AS maxrevisionid 
            FROM '. self::$prefix .'entity 
            WHERE `eid` = ?;',
            array($this->_eid)
        );

        if ($st === false) {
            return false;
        }
        $row = $st->fetchAll(PDO::FETCH_ASSOC);

        if ($row[0]['maxrevisionid'] === null) {
            // Could not retrive the newest revision id
            return false;
        } else {
            $this->_revisionid= $row[0]['maxrevisionid'];
        }
        return true;
    }

    /**
     * Retrive entity data from database
     *
     * Loads the entity data from the database. If either _eid and _revisionid
     * is not set or an error occures and the method returns false. If only
     * _eid is set, the newest revision will be fetched.
     *
     * @return PDOStatement|bool The PDOstatement executed or false is an error
     * occures.
     */
    public function load()
    {
        if (!empty($this->_eid) && is_null($this->_revisionid)) {
            if (!$this->_newestRevision()) {
                SimpleSAML_Logger::error(
                    'JANUS:Entity:load - Could not get newest revision.'
                );
                return false;
            }
        }
        if (empty($this->_eid) || is_null($this->_revisionid)) {
            SimpleSAML_Logger::error(
                'JANUS:Entity:load - entityid and revisionid needs to bes set.'
            );
            return false;
        }

        $st = $this->execute(
            'SELECT * 
            FROM '. self::$prefix .'entity 
            WHERE `eid` = ? AND `revisionid` = ?;',
            array($this->_eid, $this->_revisionid)
        );

        if ($st === false) {
            return false;
        }

        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $this->_eid = $row['eid'];
            $this->_entityid = $row['entityid'];
            $this->_revisionid = $row['revisionid'];
            $this->_workflow = $row['state'];
            $this->_type = $row['type'];
            $this->_expiration = $row['expiration'];
            $this->_metadataurl = $row['metadataurl'];
            $this->_allowedall = $row['allowedall'];
            $this->_parent = $row['parent'];
            $this->_revisionnote = $row['revisionnote'];

            $this->_modify   = false;
        }

        return $st;
    }

    /**
     * Set the eid of the entity
     *
     * A valid eid is required to save and load the entity. Set _modified to
     * true.
     *
     * @param string $eid Entity identifier
     *
     * @return void
     *
     * @since Method available since Release [FIRST RELEASE FUNCTION APPEARED IN]
     */
    public function setEid($eid)
    {
        assert('ctype_digit($eid)');

        $this->_eid = $eid;

        $this->_modified = true;
    }

    /**
     * Set entity id of entity
     *
     * Method for setting the entity id. Method sets _modified to true.
     *
     * @param string $entityid Entity id
     *
     * @return void
     *
     * @since Method available since Release 1.0.0
     */
    public function setEntityid($entityid)
    {
        assert('is_string($entityid)');

        $this->_entityid = $entityid;

        $this->_modified = true;
    }

    /**
     * Get entity metadata url.
     * Assume url = entity_id
     */
    public function geMetadataURL()
    {
        return $this->_entityid;
    }

    /**
     * Set revision id.
     *
     * Method for setting the revision id. The revision id is automaticlly
     * increased by one when the entity is saved. Method sets _modified to
     * true.
     *
     * @param int $revisionid Revision id of entity.
     *
     * @return void
     */
    public function setRevisionid($revisionid)
    {
        assert('ctype_digit($revisionid)');

        $this->_revisionid = $revisionid;

        $this->_modified = true;
    }

    /**
     * Set system for entity.
     *
     * Sets the system in which the entity resides. Method sets _modified to true.
     *
     * @param string $system System name
     *
     * @return void
     * @since Method available since Release 1.0.0
     * @deprecated Method deprecated since Release 1.1.0
     */
    public function setSystem($system)
    {
        assert('is_string($system)');

        if ($system != $this->_system) {
            $this->_system = $system;
            $this->_modified = true;
            return true;
        }
        return false;
    }

    /**
     * Set state
     *
     * Set the entity state
     *
     * @param string $state Entity state
     *
     * @return void
     * @since Method available since Release 1.0.0
     * @deprecated Method deprecated since Release 1.1.0
     */
    public function setState($state)
    {
        assert('is_string($state)');

        if ($state != $this->_state) {
            $this->_state = $state;
            $this->_modified = true;
            return true;
        }
        return false;
    }

    /**
     * Set the entity type
     *
     * The method will only return true if the new value is different from the
     * already set value.
     *
     * @param string $type Entity type
     *
     * @return bool True on change, false othervise
     * @todo Check that the type is valid
     * @todo Should return true on success, not only on change
     */
    public function setType($type)
    {
        assert('is_string($type)');

        if ($type != $this->_type) {
            $this->_type = $type;
            $this->_modified = true;
            return true;
        }
        return false;
    }

    /**
     * Retrive the entity revision id
     *
     * @return int Revision id
     */
    public function getRevisionid()
    {
        return $this->_revisionid;
    }

    /**
     * Retrive the entity identifier
     *
     * @return int The entity identifier
     * @since Method available since Release ??
     */
    public function getEid()
    {
        return $this->_eid;
    }

    /**
     * Entity entity id
     *
     * @return string Entity id
     * @isnce Method available since Release 1.0.0
     */
    public function getEntityid()
    {
        return $this->_entityid;
    }

    /**
     * Retrive entity type
     *
     * @return string Entity type
     * @since Method available since Release 1.0.0
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * Get system
     *
     * Get the entity system.
     *
     * @return string Entity system
     * @since Method available since Release 1.0.0
     * @deprecated Method deprecated since Release 1.1.0
     */
    public function getSystem()
    {
        return $this->_system;
    }

    /**
     * Get state
     *
     * Get the entity state.
     *
     * @return Entity state
     * @since Method available since Release 1.0.0
     * @deprecated Method deprecated since Release 1.1.0
     */
    public function getState()
    {
        return $this->_state;
    }

    /**
     * Set the allowedAll flag for the entity
     *
     * The function will only return true if the flag is changed.
     *
     * @param string $allowedall String 'yes'/'no'
     *
     * @return bool True on change, false othervise
     * @since      Method available since Release 1.0.0
     * @todo Should return true on success. Also if the new value is the same as
     * the old one.
     */
    public function setAllowedall($allowedall)
    {
        assert('is_string($allowedall)');

        if ($allowedall != $this->_allowedall) {
            $this->_allowedall = $allowedall;
            $this->_modified = true;
            return true;
        }
        return false;
    }

    /**
     * Retrive the allowAll flag for the entity
     *
     * @return string AllowAll flag
     * @since Method available since Release 1.0.0
     */
    public function getAllowedall()
    {
        return $this->_allowedall;
    }

    /**
     * Set entity workflow
     *
     * @param string $state Entity workflow
     *
     * @return bool True if workflow was changed
     * @since Method available since Release 1.0.0
     */
    public function setWorkflow($workflow)
    {
        assert('is_string($workflow)');

        if ($workflow != $this->_workflow) {
            $this->_workflow = $workflow;
            $this->_modified = true;
            return true;
        }
        return false;
    }

    /**
     * Get entity workflow
     *
     * @return string Entity workflow
     * @since Method available since Release 1.0.0
     */
    public function getWorkflow()
    {
        return $this->_workflow;
    }

    /**
     * Set entity parent entity
     *
     * @param int $parent Parent entity eid
     *
     * @return void
     * @since Method available since Release 1.0.0
     */
    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    /**
     * Get entity parent entity
     *
     * @return int
     * @since Method available since Release 1.0.0
     */
    public function getParent()
    {
        return $this->_parent;
    }

    /**
     * Set entity revision note
     *
     * @param string $revisionnote The revision note
     *
     * @return void
     * @since Method available since Release 1.0.0
     */
    public function setRevisionnote($revisionnote)
    {
        assert('is_string($revisionnote)');

        $this->_revisionnote = $revisionnote;
    }

    /**
     * Get entity revision note
     *
     * @return string The revision note
     * @since Method available since Release 1.0.0
     */
    public function getRevisionnote()
    {
        return $this->_revisionnote;
    }
}
?>
