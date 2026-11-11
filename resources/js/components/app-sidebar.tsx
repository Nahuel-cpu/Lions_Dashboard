import { Link } from '@inertiajs/react';
import {
    Boxes,
    ClipboardList,
    Footprints,
    LayoutDashboard,
    UsersRound,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { clientes, dashboard, inventario, pedidos, productos } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Panel',
        href: dashboard().url,
        icon: LayoutDashboard,
    },
    {
        title: 'Pedidos',
        href: pedidos().url,
        icon: ClipboardList,
    },
    {
        title: 'Productos',
        href: productos().url,
        icon: Footprints,
    },
    {
        title: 'Clientes',
        href: clientes().url,
        icon: UsersRound,
    },
    {
        title: 'Inventario',
        href: inventario().url,
        icon: Boxes,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogoIcon className="size-8 rounded-lg bg-amber-950 object-contain p-1 text-amber-400" />
                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <span className="truncate font-bold text-amber-500">
                                        LIONS
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        Panel de comercio
                                    </span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
