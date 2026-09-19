import { router, usePage } from '@inertiajs/react';
import { ChevronsUpDown, Globe, Plus } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { activeSiteUrl, sitesCreateUrl } from '@/lib/urls';

export function SiteSwitcher() {
    const { company, sites, currentSite } = usePage().props;
    const { isMobile } = useSidebar();

    if (!company || !sites || sites.length === 0) {
        return null;
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="data-[state=open]:bg-sidebar-accent"
                            tooltip={{ children: currentSite?.name ?? 'Select a site' }}
                        >
                            <div className="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-md">
                                <Globe className="size-4" />
                            </div>
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                <span className="text-muted-foreground truncate text-xs">Site</span>
                                <span className="truncate font-medium">
                                    {currentSite?.name ?? 'Select a site'}
                                </span>
                            </div>
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56"
                        align="start"
                        side={isMobile ? 'bottom' : 'right'}
                    >
                        <DropdownMenuLabel className="text-muted-foreground text-xs">
                            Sites
                        </DropdownMenuLabel>
                        {sites.map((site) => (
                            <DropdownMenuItem
                                key={site.id}
                                onSelect={() => router.put(activeSiteUrl(company.uid), { site_id: site.id })}
                                className={site.id === currentSite?.id ? 'bg-accent' : ''}
                            >
                                <Globe className="size-4" />
                                {site.name}
                            </DropdownMenuItem>
                        ))}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onSelect={() => router.visit(sitesCreateUrl(company.uid))}>
                            <Plus className="size-4" />
                            Add site
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
