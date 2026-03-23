import { useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, ToggleControl } from '@wordpress/components';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews/wp';

const formatTimestamp = ( value ) => {
	if ( ! value ) {
		return '';
	}

	const parsed = new Date( value );
	if ( Number.isNaN( parsed.getTime() ) ) {
		return value;
	}

	return parsed.toLocaleString( undefined, {
		dateStyle: 'medium',
		timeStyle: 'medium',
	} );
};

const truncate = ( value, maxLen ) => {
	const text = String( value || '' );
	if ( text.length <= maxLen ) {
		return text;
	}

	return text.slice( 0, maxLen ) + '...';
};

const getDefaultView = () => ( {
	type: 'table',
	perPage: 25,
	fields: [ 'timestamp', 'service', 'method', 'status', 'path', 'summary' ],
	sort: {
		field: 'timestamp',
		direction: 'desc',
	},
} );

const getStatusTone = ( value ) => {
	const status = Number.parseInt( String( value || '' ), 10 );
	if ( Number.isNaN( status ) ) {
		return 'unknown';
	}

	if ( status >= 200 && status < 300 ) {
		return 'success';
	}

	if ( status >= 300 && status < 400 ) {
		return 'redirect';
	}

	if ( status >= 400 && status < 500 ) {
		return 'warning';
	}

	if ( status >= 500 ) {
		return 'error';
	}

	return 'unknown';
};

const isFailedEntry = ( item ) => {
	const status = Number.parseInt( String( item?.status || '' ), 10 );

	if ( ! Number.isNaN( status ) && status >= 400 ) {
		return true;
	}

	return !! ( item?.raw_error && String( item.raw_error ).trim() );
};

export const LogViewerApp = ( { config } ) => {
	const [ rawItems ] = useState(
		Array.isArray( config?.items ) ? config.items : []
	);
	const [ selectedItem, setSelectedItem ] = useState( null );
	const [ failedOnly, setFailedOnly ] = useState( false );
	const [ view, setView ] = useState( getDefaultView() );

	const visibleItems = useMemo( () => {
		if ( ! failedOnly ) {
			return rawItems;
		}

		return rawItems.filter( isFailedEntry );
	}, [ rawItems, failedOnly ] );

	const fields = useMemo(
		() => [
			{
				id: 'timestamp',
				label: __( 'Timestamp', 'big-orange-pardot' ),
				enableGlobalSearch: true,
				render: ( { item } ) => formatTimestamp( item.timestamp ),
			},
			{
				id: 'service',
				label: __( 'Service', 'big-orange-pardot' ),
				enableGlobalSearch: true,
			},
			{
				id: 'method',
				label: __( 'Method', 'big-orange-pardot' ),
				enableGlobalSearch: true,
			},
			{
				id: 'status',
				label: __( 'Status', 'big-orange-pardot' ),
				enableGlobalSearch: true,
				render: ( { item } ) => {
					const label = item.status || '—';
					const tone = getStatusTone( item.status );

					return (
						<span
							className={ `bol-log-status bol-log-status--${ tone }` }
						>
							{ label }
						</span>
					);
				},
			},
			{
				id: 'path',
				label: __( 'Endpoint', 'big-orange-pardot' ),
				enableGlobalSearch: true,
				render: ( { item } ) => {
					if ( ! item.url ) {
						return item.path || '';
					}

					return (
						<a
							href={ item.url }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ item.path || item.url }
						</a>
					);
				},
			},
			{
				id: 'request_summary',
				label: __( 'Request', 'big-orange-pardot' ),
				enableGlobalSearch: true,
			},
			{
				id: 'summary',
				label: __( 'Summary', 'big-orange-pardot' ),
				enableGlobalSearch: true,
				render: ( { item } ) => (
					<span className="bol-log-summary">
						{ truncate( item.summary, 220 ) }
					</span>
				),
			},
		],
		[]
	);

	const actions = useMemo(
		() => [
			{
				id: 'inspect-entry',
				label: __( 'Inspect entry', 'big-orange-pardot' ),
				isEligible: ( item ) => Boolean( item ),
				callback: ( [ item ] ) => {
					if ( item ) {
						setSelectedItem( item );
					}
				},
			},
			{
				id: 'open-url',
				label: __( 'Open request URL', 'big-orange-pardot' ),
				isEligible: ( item ) => Boolean( item?.url ),
				callback: ( [ item ] ) => {
					if ( item?.url ) {
						window.open(
							item.url,
							'_blank',
							'noopener,noreferrer'
						);
					}
				},
			},
		],
		[]
	);

	const { data: processedData, paginationInfo } = useMemo(
		() => filterSortAndPaginate( visibleItems, view, fields ),
		[ visibleItems, view, fields ]
	);

	return (
		<div className="bol-log-viewer-app">
			<div className="bol-log-viewer-controls">
				<Button
					variant="secondary"
					onClick={ () => window.location.reload() }
				>
					{ __( 'Refresh logs', 'big-orange-pardot' ) }
				</Button>
				<div className="bol-log-viewer-toggle-wrap">
					<ToggleControl
						label={ __(
							'Show only failed requests',
							'big-orange-pardot'
						) }
						checked={ failedOnly }
						onChange={ ( nextValue ) =>
							setFailedOnly( !! nextValue )
						}
					/>
				</div>
			</div>

			{ visibleItems.length > 0 ? (
				<DataViews
					data={ processedData }
					fields={ fields }
					view={ view }
					onChangeView={ setView }
					defaultLayouts={ { table: { layout: {} } } }
					actions={ actions }
					paginationInfo={ paginationInfo }
				/>
			) : (
				<p>{ __( 'No log entries found.', 'big-orange-pardot' ) }</p>
			) }

			{ selectedItem ? (
				<div className="bol-log-entry-inspector">
					<h3>{ __( 'Selected Entry', 'big-orange-pardot' ) }</h3>
					<p>{ truncate( selectedItem.summary || '', 1000 ) }</p>
					{ selectedItem.raw_error ? (
						<details>
							<summary>
								{ __( 'Error', 'big-orange-pardot' ) }
							</summary>
							<pre>{ String( selectedItem.raw_error ) }</pre>
						</details>
					) : null }
					<details>
						<summary>
							{ __( 'Request payload', 'big-orange-pardot' ) }
						</summary>
						<pre>{ String( selectedItem.raw_request || '' ) }</pre>
					</details>
					<details>
						<summary>
							{ __( 'Response payload', 'big-orange-pardot' ) }
						</summary>
						<pre>{ String( selectedItem.raw_response || '' ) }</pre>
					</details>
				</div>
			) : null }
		</div>
	);
};
