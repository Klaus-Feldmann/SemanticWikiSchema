export interface PageTitleResult {
	pageId: number;
	title: string;
	displayTitle?: string;
	url?: string;
	description?: string;
	thumbnail?: { url: string; width?: number; height?: number };
	redirectFrom?: string;
}

export interface PageTitleSearch {

	searchPageTitles( search: string, limit: number, namespace?: number ): Promise<PageTitleResult[]>;

}
