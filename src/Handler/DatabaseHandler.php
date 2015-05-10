<?php
/**
 * Part of the Joomla Framework Session Package
 *
 * @copyright  Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Session\Handler;

use Joomla\Database\DatabaseDriver;
use Joomla\Session\HandlerInterface;

/**
 * Database session storage handler
 *
 * @since  __DEPLOY_VERSION__
 */
class DatabaseHandler implements HandlerInterface
{
	/**
	 * Database connector
	 *
	 * @var    \Joomla\Database\DatabaseDriver
	 * @since  __DEPLOY_VERSION__
	 */
	private $db;

	/**
	 * Flag whether gc() has been called
	 *
	 * @var    boolean
	 * @since  __DEPLOY_VERSION__
	 */
	private $gcCalled = false;

	/**
	 * Lifetime for garbage collection
	 *
	 * @var    integer
	 * @since  __DEPLOY_VERSION__
	 */
	private $gcLifetime;

	/**
	 * Constructor
	 *
	 * @param   DatabaseDriver  $db  Database connector
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct(DatabaseDriver $db)
	{
		$this->db = $db;
	}

	/**
	 * Close the session
	 *
	 * @return  boolean  True on success, false otherwise
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function close()
	{
		if ($this->gcCalled)
		{
			$this->gcCalled   = false;
			$this->gcLifetime = null;

			$query = $this->db->getQuery(true)
				->delete($this->db->quoteName('#__session'))
				->where($this->db->quoteName('time') . ' < ' . $this->db->quote((int) $this->gcLifetime));

			// Remove expired sessions from the database.
			$this->db->setQuery($query)->execute();
		}

		$this->db->disconnect();

		return true;
	}

	/**
	 * Destroy a session
	 *
	 * @param   integer  $session_id  The session ID being destroyed
	 *
	 * @return  boolean  True on success, false otherwise
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function destroy($session_id)
	{
		try
		{
			$query = $this->db->getQuery(true)
				->delete($this->db->quoteName('#__session'))
				->where($this->db->quoteName('session_id') . ' = ' . $this->db->quote($session_id));

			// Remove a session from the database.
			$this->db->setQuery($query)->execute();

			return true;
		}
		catch (\Exception $e)
		{
			return false;
		}
	}

	/**
	 * Cleanup old sessions
	 *
	 * @param   integer  $maxlifetime  Sessions that have not updated for the last maxlifetime seconds will be removed
	 *
	 * @return  boolean  True on success, false otherwise
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function gc($maxlifetime)
	{
		// We'll delay garbage collection until the session is closed to prevent potential issues mid-cycle
		$this->gcLifetime = time() - $maxlifetime;
		$this->gcCalled   = true;

		return true;
	}

	/**
	 * Test to see if the HandlerInterface is available
	 *
	 * @return  boolean  True on success, false otherwise
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function isSupported()
	{
		return class_exists('Joomla\\Database\\DatabaseDriver');
	}

	/**
	 * Initialize session
	 *
	 * @param   string  $save_path   The path where to store/retrieve the session
	 * @param   string  $session_id  The session id
	 *
	 * @return  boolean  True on success, false otherwise
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function open($save_path, $session_id)
	{
		$this->db->connect();

		return true;
	}

	/**
	 * Read session data
	 *
	 * @param   string  $session_id  The session id to read data for
	 *
	 * @return  string  The session data
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function read($session_id)
	{
		try
		{
			// Get the session data from the database table.
			$query = $this->db->getQuery(true)
				->select($this->db->quoteName('data'))
				->from($this->db->quoteName('#__session'))
				->where($this->db->quoteName('session_id') . ' = ' . $this->db->quote($id));

			$this->db->setQuery($query);

			return (string) $this->db->loadResult();
		}
		catch (\Exception $e)
		{
			return '';
		}
	}

	/**
	 * Write session data
	 *
	 * @param   string  $session_id    The session id
	 * @param   string  $session_data  The encoded session data
	 *
	 * @return  boolean  True on success, false otherwise
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function write($session_id, $session_data)
	{
		try
		{
			$query = $this->db->getQuery(true)
				->update($this->db->quoteName('#__session'))
				->set($this->db->quoteName('data') . ' = ' . $this->db->quote($session_data))
				->set($this->db->quoteName('time') . ' = ' . $this->db->quote((int) time()))
				->where($this->db->quoteName('session_id') . ' = ' . $this->db->quote($session_id));

			// Try to update the session data in the database table.
			$this->db->setQuery($query)->execute();

			return true;
		}
		catch (\Exception $e)
		{
			return false;
		}
	}
}
