import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Legend,
    Line,
    LineChart,
    Pie,
    PieChart,
    PolarAngleAxis,
    PolarGrid,
    PolarRadiusAxis,
    Radar,
    RadarChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { BRAND, tooltipStyle, Card, EmptyState } from './ui';

/* ===========================================================================
 * Chart wrappers — every dashboard graph uses these so tooltips, grid
 * colours and brand palette stay 100% consistent across all 6 roles.
 * ======================================================================== */

/** Branded card shell sized for mobile-first charts. */
export function ChartCard({ title, subtitle, action, children, delay = 0, className = '' }) {
    return (
        <Card title={title} subtitle={subtitle} action={action} delay={delay} className={className}>
            <div className="chart-box">{children}</div>
        </Card>
    );
}

const axis = { stroke: BRAND.muted, fontSize: 11, fontWeight: 600 };
const gridProps = { stroke: BRAND.grid, strokeDasharray: '4 4' };

/** Line + area trend (enrollment growth, weekly attendance). */
export function TrendChart({ data, xKey, yKey, color = BRAND.primary, unit = '' }) {
    if (!data?.length) {
        return <EmptyState title="No trend data yet" hint="Data appears once activity is recorded." />;
    }

    return (
        <ResponsiveContainer width="100%" height="100%">
            <LineChart data={data} margin={{ top: 6, right: 8, left: -18, bottom: 0 }}>
                <CartesianGrid {...gridProps} vertical={false} />
                <XAxis dataKey={xKey} tickLine={false} axisLine={false} tick={axis} />
                <YAxis tickLine={false} axisLine={false} tick={axis} allowDecimals={false} />
                <Tooltip contentStyle={tooltipStyle} cursor={{ stroke: color, strokeWidth: 1 }} />
                <Line
                    type="monotone"
                    dataKey={yKey}
                    stroke={color}
                    strokeWidth={3}
                    dot={{ r: 4, fill: color, strokeWidth: 0 }}
                    activeDot={{ r: 6 }}
                    name={`${yKey}${unit ? ` (${unit})` : ''}`}
                />
            </LineChart>
        </ResponsiveContainer>
    );
}

/** Vertical bars (class comparison, user distribution). */
export function BarChartCard({ data, xKey, yKey, color = BRAND.primary, unit = '' }) {
    if (!data?.length) return <EmptyState title="No data to compare yet" />;

    return (
        <ResponsiveContainer width="100%" height="100%">
            <BarChart data={data} margin={{ top: 6, right: 8, left: -18, bottom: 0 }}>
                <CartesianGrid {...gridProps} vertical={false} />
                <XAxis dataKey={xKey} tickLine={false} axisLine={false} tick={axis} />
                <YAxis tickLine={false} axisLine={false} tick={axis} allowDecimals={false} />
                <Tooltip contentStyle={tooltipStyle} cursor={{ fill: 'rgba(216,27,96,0.06)' }} />
                <Bar
                    dataKey={yKey}
                    fill={color}
                    radius={[8, 8, 0, 0]}
                    maxBarSize={46}
                    name={`${yKey}${unit ? ` (${unit})` : ''}`}
                />
            </BarChart>
        </ResponsiveContainer>
    );
}

/** Grouped bars (income vs expenses). */
export function GroupedBarChart({ data, xKey, series, unit = '' }) {
    if (!data?.length) return <EmptyState title="No financial history yet" />;

    return (
        <ResponsiveContainer width="100%" height="100%">
            <BarChart data={data} margin={{ top: 6, right: 8, left: -10, bottom: 0 }}>
                <CartesianGrid {...gridProps} vertical={false} />
                <XAxis dataKey={xKey} tickLine={false} axisLine={false} tick={axis} />
                <YAxis tickLine={false} axisLine={false} tick={axis} />
                <Tooltip
                    contentStyle={tooltipStyle}
                    cursor={{ fill: 'rgba(30,41,59,0.05)' }}
                    formatter={(value) => [`${unit} ${Number(value).toLocaleString()}`, undefined]}
                />
                <Legend wrapperStyle={{ fontSize: 12, fontWeight: 700 }} iconType="circle" iconSize={9} />
                {series.map((s) => (
                    <Bar key={s.key} dataKey={s.key} fill={s.color} radius={[6, 6, 0, 0]} maxBarSize={30} />
                ))}
            </BarChart>
        </ResponsiveContainer>
    );
}

/** Doughnut (fee collection, attendance split). */
export function DonutChart({ data, colors = [BRAND.primary, BRAND.accent, BRAND.secondary, '#F43F5E'] }) {
    const total = (data ?? []).reduce((sum, slice) => sum + Number(slice.value || 0), 0);
    if (!total) return <EmptyState title="Nothing to summarise yet" />;

    return (
        <ResponsiveContainer width="100%" height="100%">
            <PieChart>
                <Pie
                    data={data}
                    dataKey="value"
                    nameKey="name"
                    innerRadius="62%"
                    outerRadius="88%"
                    paddingAngle={3}
                    stroke="none"
                >
                    {data.map((slice, i) => (
                        <Cell key={slice.name} fill={colors[i % colors.length]} />
                    ))}
                </Pie>
                <Tooltip contentStyle={tooltipStyle} />
                <Legend wrapperStyle={{ fontSize: 12, fontWeight: 700 }} iconType="circle" iconSize={9} />
            </PieChart>
        </ResponsiveContainer>
    );
}

/** Radar (spiritual milestone dimensions, subject mastery). */
export function RadarChartCard({ data, xKey, series, color = BRAND.secondary }) {
    if (!data?.length) return <EmptyState title="No dimensions recorded yet" />;

    return (
        <ResponsiveContainer width="100%" height="100%">
            <RadarChart data={data} outerRadius="72%">
                <PolarGrid stroke={BRAND.grid} />
                <PolarAngleAxis
                    dataKey={xKey}
                    tick={{ fill: BRAND.muted, fontSize: 11, fontWeight: 700 }}
                />
                <PolarRadiusAxis tick={false} axisLine={false} />
                {series.map((key) => (
                    <Radar
                        key={key}
                        dataKey={key}
                        stroke={color}
                        fill={color}
                        fillOpacity={0.25}
                        strokeWidth={2}
                    />
                ))}
                <Tooltip contentStyle={tooltipStyle} />
            </RadarChart>
        </ResponsiveContainer>
    );
}
