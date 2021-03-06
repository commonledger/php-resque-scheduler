<?php
/**
 * ResqueRepeater - managing recurring/repeating jobs in resque
 *
 * Based on the php-resque-scheduler library by Chris Boulton
 *
 * @package		ResqueRepeater
 * @author		API Team <api@commonledger.com>
 * @copyright	(c) 2014 Common Ledger
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class ResqueRepeater_Worker
{
	const LOG_NONE = 0;
	const LOG_NORMAL = 1;
	const LOG_VERBOSE = 2;
    const LOG_VVERBOSE = 3;
	
	/**
	 * @var int Current log level of this worker.
	 */
	public $logLevel = 0;
	
	/**
	 * @var int Interval to sleep for between checking schedules.
	 */
	protected $interval = 5;
	
	/**
	* The primary loop for a worker.
	*
	* Every $interval (seconds), the scheduled queue will be checked for jobs
	* that should be pushed to Resque.
	*
	* @param int $interval How often to check schedules.
	*/
	public function work($interval = null)
	{
		if ($interval !== null) {
			$this->interval = $interval;
		}

		$this->updateProcLine('Starting');
		
		while (true) {
			$this->handleDelayedItems();
			$this->sleep();
		}
	}
	
	/**
	 * Handle delayed items for the next scheduled timestamp.
	 *
	 * Searches for any items that are due to be scheduled in Resque
	 * and adds them to the appropriate job queue in Resque.
	 *
	 * @param DateTime|int $timestamp Search for any items up to this timestamp to schedule.
	 */
	public function handleDelayedItems($timestamp = null)
	{
        $this->log('checking for repeating items to be executed', self::LOG_VVERBOSE);
        while (($timestamp = ResqueRepeater::nextDelayedTimestamp($timestamp)) !== false) {
			$this->updateProcLine('Processing Delayed Items');
			$this->enqueueDelayedItemsForTimestamp($timestamp);
		}
	}
	
	/**
	 * Schedule all of the delayed jobs for a given timestamp.
	 *
	 * Searches for all items for a given timestamp, pulls them off the list of
	 * delayed jobs and pushes them across to Resque.
	 *
	 * @param DateTime|int $timestamp Search for any items up to this timestamp to schedule.
	 */
	public function enqueueDelayedItemsForTimestamp($timestamp)
	{
		$item = null;
		while ($item = ResqueRepeater::nextItemForTimestamp($timestamp)) {
			$this->log('queueing ' . $item['class'] . ' in ' . $item['queue'] .' [delayed]');
			
			Resque_Event::trigger('beforeDelayedEnqueue', array(
				'queue' => $item['queue'],
				'class' => $item['class'],
				'args'  => $item['args'],
			));

			$payload = array_merge(array($item['queue'], $item['class']), $item['args']);
			call_user_func_array('Resque::enqueue', $payload);
		}
	}
	
	/**
	 * Sleep for the defined interval.
	 */
	protected function sleep()
	{
		sleep($this->interval);
	}
	
	/**
	 * Update the status of the current worker process.
	 *
	 * On supported systems (with the PECL proctitle module installed), update
	 * the name of the currently running process to indicate the current state
	 * of a worker.
	 *
	 * @param string $status The updated process title.
	 */
	private function updateProcLine($status)
	{
		if(function_exists('setproctitle')) {
			setproctitle('resque-scheduler-' . ResqueRepeater::VERSION . ': ' . $status);
		}
	}
	
	/**
	 * Output a given log message to STDOUT.
	 *
	 * @param string $message Message to output.
	 */
	public function log($message, $level = self::LOG_NORMAL)
	{
        if($this->logLevel < $level) return;

		if($this->logLevel == self::LOG_NORMAL) {
			fwrite(STDOUT, "*** " . $message . "\n");
		}
		else if($this->logLevel >= self::LOG_VERBOSE) {
			fwrite(STDOUT, "** [" . strftime('%T %Y-%m-%d') . "] " . $message . "\n");
		}
	}
}
