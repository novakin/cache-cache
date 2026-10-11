<?php
/** Owned link controls shared by modeled and disposable native uninstall fixtures. */
declare(strict_types=1);

function uninstall_create_directory_link( string $target, string $entry ): void {
	if ( file_exists( $entry ) || is_link( $entry ) || ! is_dir( $target ) ) {
		throw new RuntimeException( 'Link control requires a new entry and an owned target.' );
	}
	if ( 'Windows' === PHP_OS_FAMILY ) {
		$environment = array_merge( getenv(), [ 'NOOBROCKET_TEST_LINK' => $entry, 'NOOBROCKET_TEST_TARGET' => $target ] );
		$worker = proc_open(
			[ 'powershell.exe', '-NoProfile', '-NonInteractive', '-Command', 'New-Item -ItemType Junction -Path $env:NOOBROCKET_TEST_LINK -Target $env:NOOBROCKET_TEST_TARGET -ErrorAction Stop | Out-Null' ],
			[ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'redirect', 1 ] ], $pipes, null, $environment
		);
		if ( ! is_resource( $worker ) ) { throw new RuntimeException( 'Cannot start Windows junction control.' ); }
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		fclose( $pipes[1] );
		if ( 0 !== proc_close( $worker ) ) { throw new RuntimeException( 'Windows junction creation failed: ' . substr( $output, 0, 1000 ) ); }
	} elseif ( ! symlink( $target, $entry ) ) {
		throw new RuntimeException( 'Linux directory symlink control is required.' );
	}
	clearstatcache( true, $entry );
	if ( realpath( $entry ) !== realpath( $target ) || ( 'Windows' === PHP_OS_FAMILY && is_link( $entry ) ) ) {
		throw new RuntimeException( 'Directory link control did not establish the expected platform boundary.' );
	}
}

function uninstall_remove_directory_link( string $target, string $entry ): void {
	if ( ! file_exists( $entry ) && ! is_link( $entry ) ) { return; }
	clearstatcache( true, $entry );
	if ( realpath( $entry ) !== realpath( $target ) ) { throw new RuntimeException( 'Owned link entry target changed before cleanup.' ); }
	// Non-recursive removal detaches only our entry; never enumerate the target.
	$removed = 'Windows' === PHP_OS_FAMILY ? rmdir( $entry ) : unlink( $entry );
	if ( ! $removed || ! is_dir( $target ) ) { throw new RuntimeException( 'Owned link entry cleanup failed.' ); }
}

function uninstall_remove_owned_tree( string $root ): void {
	if ( ! is_dir( $root ) ) { return; }
	if ( realpath( $root ) !== $root ) { throw new RuntimeException( 'Owned cleanup root changed.' ); }
	foreach ( new FilesystemIterator( $root, FilesystemIterator::SKIP_DOTS ) as $entry ) {
		$path = $entry->getPathname();
		if ( $entry->isDir() ) {
			if ( realpath( $path ) !== realpath( $root ) . DIRECTORY_SEPARATOR . $entry->getFilename() ) { throw new RuntimeException( 'Detach owned link controls before recursive cleanup.' ); }
			uninstall_remove_owned_tree( realpath( $path ) );
		} else { unlink( $path ); }
	}
	rmdir( $root );
}
