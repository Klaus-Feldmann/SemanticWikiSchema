import type { RightsFetcher } from '@/persistence/UserObjectBasedRightsFetcher';
import type { MappingPermissionHints } from '@/application/MappingPermissionHints';

export class RightsBasedMappingPermissionHints implements MappingPermissionHints {

	public constructor( private readonly rightsFetcher: RightsFetcher ) {
	}

	public async canCreateMappings(): Promise<boolean> {
		const rights = await this.rightsFetcher.getRights();
		return rights.includes( 'sws-mapping-edit' );
	}

	public async canEditMapping( _mappingName: string ): Promise<boolean> {
		const rights = await this.rightsFetcher.getRights();
		return rights.includes( 'sws-mapping-edit' );
	}

	// Deleting a Mapping page is a normal MediaWiki page deletion, authorized by the core `delete`
	// right alone (the `sws-mapping-edit` namespace protection does not cover deletion). Checked
	// on its own so a delete-capable group without `sws-mapping-edit` still sees the button.
	public async canDeleteMapping( _mappingName: string ): Promise<boolean> {
		const rights = await this.rightsFetcher.getRights();
		return rights.includes( 'delete' );
	}

}
