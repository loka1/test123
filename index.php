<?php

include_once 'vendor/autoload.php';

date_default_timezone_set( 'UTC' );

$start = new UpdateReport();

class UpdateReport {

	public $api;

	public function __construct( $projects = null ) {
		$this->api = new ApiHelper();
		$configfn = new GetConfig();
		logToFile( 'Running new cycle. Fetching config.' );
		$config = $configfn->getJSONConfig( 'User:Community Tech bot/Popular pages config.json' );
		if ( isset( $projects ) ) {
			foreach ( $config as $project => $info ) {
				if ( !in_array( $info['Name'], $projects ) ) {
					unset( $config[$project] ); // Only keep projects we need
				}
			}
		}
		logToFile( 'Number of projects pending update: ' . count( $config ) );
		$this->updateReports( $config );
	}

	public function updateReports( $config ) {
		foreach ( $config as $project => $info ) {
			logToFile( 'Beginning to process: ' . $project );
			// Check the project exists
			if ( !$this->api->doesTitleExist( $project ) ) {
				logToFile( 'Error: Project page for ' . $project . ' does not exist!' );
				continue;
			}
			// Check config is not empty
			if ( !isset( $info['Name'] ) || !isset( $info['Limit'] ) || !isset( $info['Report'] ) ) {
				logToFile( 'Incomplete data in config. Aborting.' );
				continue;
			}
			$pages = $this->api->getProjectPages( $info['Name'] ); // Returns { \'title\' => array( \'class\' => \'\', \'importance\' => \'\' ),... }
			$start = strtotime( 'first day of previous month' );
			$end = strtotime( 'last day of previous month' );
			$views = $this->api->getMonthlyPageviews( array_keys( $pages ), date( 'Ymd00', $start ), date( 'Ymd00', $end ) );
			$views = array_slice( $views, 0, $info['Limit'], true );
			$output = '
This is a list of pages in the scope of ' . $project . ' along with pageviews.

To report bugs, please write on the [[meta:User_talk:Community_Tech_bot| Community tech bot]] talk page on Meta.

Period: ' . date( 'Y-m-d', $start ) . ' to ' . date( 'Y-m-d', $end ) . '.

Updated on: ~~~~~

{| class="wikitable sortable" style="text-align: left;"
! Rank
! Page title
! Views
! Views per day (average)
! Assessment
! Importance
! Link to pageviews tool
|-
';
			$index = 1;
			foreach ( $views as $title => $view ) {
				$output .= '| ' . $index . '
| [[' . $title . ']]
| ' . $view . '
| ' . floor( $view / ( floor( ( $end - $start ) / ( 60 * 60 * 24 ) ) ) ) . '
{{class|' . $pages[$title]['class'] . '}}
{{importance|' . $pages[$title]['importance'] . '}}
| [https://tools.wmflabs.org/pageviews/?project=en.wikipedia.org&start=' . date( 'Y-m-d', $start ) . '&end=' . date( 'Y-m-d', $end ) . '&pages=' . str_replace( ' ', '_', $title ) . ' Link]
|-
';
				$index++;
			}
			$output .= '|}';
			// Update report text on wiki page
			$this->api->setText( $info['Report'], $output );
			// Update database for the project
			$this->api->updateDB( $info['Name'] );
			logToFile( 'Finished processing: ' . $project );
		}
	}
}



