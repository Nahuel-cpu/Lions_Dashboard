export type Kpis = {
    revenue: number;
    profit: number;
    margin: number;
    orders: number;
    pending: number;
    completed: number;
    cancelled: number;
    avg_ticket: number;
    new_customers: number;
    returning_customers: number;
    revenue_delta: number;
};

export type SeriePoint = {
    bucket: string;
    revenue: number;
    orders: number;
};

export type TopProduct = {
    product: string;
    category: string;
    units: number;
    revenue: number;
};

export type MapPoint = {
    city: string;
    lat: number;
    lng: number;
    revenue: number;
    orders: number;
};

export type StockAlert = {
    id: number;
    product: string;
    sku: string;
    size: number;
    stock: number;
    threshold: number;
};

export type ActivityItem = {
    id: number;
    type: string;
    title: string;
    description: string;
    at: string;
};

export type CategoryShare = {
    category: string;
    revenue: number;
    units: number;
};

export type TopCity = {
    city: string;
    orders: number;
    revenue: number;
};

export type DashboardFilters = {
    period: number;
    granularidad: 'dia' | 'semana' | 'mes';
    categoria: number | null;
};

export type DashboardProps = {
    kpis: Kpis;
    serie: SeriePoint[];
    top: TopProduct[];
    mapa: MapPoint[];
    alertas: StockAlert[];
    actividad: ActivityItem[];
    categorias: { id: number; name: string }[];
    categoriaShare: CategoryShare[];
    topCiudades: TopCity[];
    filters: DashboardFilters;
    updatedAt: string;
};

export type OrderStatus = 'pendiente' | 'completado' | 'cancelado';

export type Paginated<T> = {
    data: T[];
    links: {
        first: string;
        last: string;
        next: string | null;
        prev: string | null;
    };
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

export type OrderRow = {
    id: number;
    status: OrderStatus;
    total: number;
    ordered_at: string;
    shopify_id: string | null;
    customer: { id: number; name: string; email: string; city: string };
};

export type OrdersProps = {
    orders: Paginated<OrderRow>;
    counts: Record<'todos' | 'pendiente' | 'completado' | 'cancelado', number>;
    todayRevenue: number;
    filters: { estado: string; q: string | null };
};

export type ProductRow = {
    id: number;
    sku: string;
    name: string;
    category: string;
    sale_price: number;
    low_stock_threshold: number;
    units: number | string;
    revenue: number | string;
    variants: { id: number; stock: number; size: number }[];
};

export type ProductsProps = {
    products: Paginated<ProductRow>;
    categorias: { id: number; name: string }[];
    periodo: number;
    filters: { categoria: number | null; orden: string; q: string | null };
};

export type InventoryVariant = {
    id: number;
    size: number;
    stock: number;
    product_id: number;
    name: string;
    sku: string;
    sale_price: number;
    low_stock_threshold: number;
    category: string;
};

export type InventoryProps = {
    variants: Paginated<InventoryVariant>;
    alertas: StockAlert[];
    resumen: { total_stock: number; valor: number; sku_bajo: number };
    categorias: { id: number; name: string }[];
    filters: {
        categoria: number | null;
        solo_low: boolean;
        q: string | null;
        orden: string;
    };
};

export type CustomerRow = {
    id: number;
    name: string;
    email: string;
    city: string;
    created_at: string;
    completed_orders: number;
    pending_orders: number;
    total_orders: number;
    revenue: number;
    last_order: string | null;
};

export type CustomersProps = {
    customers: Paginated<CustomerRow>;
    summary: {
        nuevos: number;
        recurrentes: number;
        total: number;
        ticket_promedio: number;
    };
    periodoDias: number;
    filters: { tipo: string; orden: string; q: string | null };
};

export type LiveData = {
    orderCode: string;
    customerName: string;
    total: number;
    city: string;
    items: number;
    at: string;
};

export type StatusData = {
    orderCode: string;
    from: string;
    to: string;
    at: string;
};

export type StockData = {
    productName: string;
    size: number;
    stock: number;
    at: string;
};
