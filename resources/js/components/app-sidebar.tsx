import { Link, usePage } from "@inertiajs/react";
import {
    BookOpen,
    CreditCard,
    FlaskConical,
    FolderGit2,
    LayoutGrid,
    Grid3x3,
    MousePointerClick,
    Split,
    ShieldCheck,
    ShoppingBag,
    Users,
    Video,
} from "lucide-react";
import AppLogo from "@/components/app-logo";
import { NavFooter } from "@/components/nav-footer";
import { NavMain } from "@/components/nav-main";
import { NavUser } from "@/components/nav-user";
import { SiteSwitcher } from "@/components/site-switcher";
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from "@/components/ui/sidebar";
import {
    accountUsersUrl,
    accountsIndexUrl,
    adminUsersUrl,
    billingUrl,
    experimentsAbIndexUrl,
    experimentsMvtIndexUrl,
    experimentsSplitUrlIndexUrl,
    siteHeatmapUrl,
    shopifyIntegrationUrl,
    siteRecordingsUrl,
    siteOverviewUrl,
    sitesIndexUrl,
} from "@/lib/urls";
import { dashboard } from "@/routes";
import type { NavItem } from "@/types";

const footerNavItems: NavItem[] = [
    {
        title: "Repository",
        href: "https://github.com/laravel/react-starter-kit",
        icon: FolderGit2,
    },
    {
        title: "Documentation",
        href: "https://laravel.com/docs/starter-kits#react",
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth, company, companyRole, currentSite } = usePage().props;

    const siteNavItems: NavItem[] = [];
    const mainNavItems: NavItem[] = [];

    if (company && currentSite) {
        siteNavItems.push(
            {
                title: "Overview",
                href: siteOverviewUrl(company.uid),
                icon: LayoutGrid,
            },
            {
                title: "A/B",
                href: experimentsAbIndexUrl(company.uid),
                icon: FlaskConical,
            },
            {
                title: "Multivariate",
                href: experimentsMvtIndexUrl(company.uid),
                icon: Grid3x3,
            },
            {
                title: "Split URL",
                href: experimentsSplitUrlIndexUrl(company.uid),
                icon: Split,
            },
            {
                title: "Heatmap",
                href: siteHeatmapUrl(company.uid),
                icon: MousePointerClick,
            },
            {
                title: "Recordings",
                href: siteRecordingsUrl(company.uid),
                icon: Video,
            },
            {
                title: "Shopify",
                href: shopifyIntegrationUrl(company.uid),
                icon: ShoppingBag,
            },
        );
    }

    if (company) {
        mainNavItems.push(
            {
                title: "Sites",
                href: sitesIndexUrl(company.uid),
                icon: LayoutGrid,
            },
            { title: "Users", href: accountUsersUrl(company.uid), icon: Users },
        );

        if (companyRole === "owner" || auth.user.is_super_admin) {
            mainNavItems.push({
                title: "Billing",
                href: billingUrl(company.uid),
                icon: CreditCard,
            });
        }
    }

    if (auth.user.is_super_admin) {
        mainNavItems.push(
            { title: "Accounts", href: accountsIndexUrl(), icon: ShieldCheck },
            { title: "All users", href: adminUsersUrl(), icon: Users },
        );
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SiteSwitcher />
            </SidebarHeader>

            <SidebarContent className="gap-4">
                {siteNavItems.length > 0 && (
                    <NavMain items={siteNavItems} label="Site" />
                )}
                <NavMain items={mainNavItems} label="Account" />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
