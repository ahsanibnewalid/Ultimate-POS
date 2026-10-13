"use client";
import { useState } from "react";
import { BarChart3, Boxes, CreditCard, LayoutDashboard, Package, Settings, ShoppingCart, Users } from "lucide-react";
const stats = [{label:"Today's sales",value:"৳ 0.00",note:"No transactions yet",icon:CreditCard},{label:"Products",value:"0",note:"Catalog ready to build",icon:Package},{label:"Low stock",value:"0",note:"No alerts yet",icon:Boxes},{label:"Customers",value:"0",note:"Customer module planned",icon:Users}];
const nav = [{label:"Overview",icon:LayoutDashboard},{label:"Point of sale",icon:ShoppingCart},{label:"Products",icon:Package},{label:"Inventory",icon:Boxes},{label:"Customers",icon:Users},{label:"Reports",icon:BarChart3},{label:"Settings",icon:Settings}];
export default function Home() {
 const [active,setActive]=useState("Overview");
 return <main className="shell">
  <aside className="sidebar"><div className="brand"><span className="brand-mark">U</span><span>Ultimate<span className="brand-muted">POS</span></span></div>
   <div className="workspace"><div className="workspace-icon">S</div><div><b>My Store</b><small>Starter workspace</small></div><span className="chevron">⌄</span></div>
   <small className="nav-label">WORKSPACE</small>{nav.map(({label,icon:Icon})=><button key={label} className={"nav-item "+(active===label?"selected":"")} onClick={()=>setActive(label)}><Icon size={18}/><span>{label}</span></button>)}
   <div className="sidebar-bottom"><div className="avatar">A</div><div><b>Workspace admin</b><small>Starter plan</small></div><span className="dots">•••</span></div>
  </aside><section className="content"><header className="topbar"><div className="breadcrumbs">Workspace <span>/</span> <b>{active}</b></div><div className="avatar small">A</div></header>
   <div className="page-wrap"><div className="page-heading"><div><p className="eyebrow">YOUR BUSINESS AT A GLANCE</p><h1>{active}</h1><p className="subheading">Here’s what’s happening with your store today.</p></div><button className="primary" onClick={()=>setActive("Point of sale")}><ShoppingCart size={17}/> Open POS</button></div>
    <div className="notice"><span className="notice-dot"/><div><b>Welcome to your workspace</b><p>This is your starting dashboard. Connect the API and add products to begin tracking sales.</p></div><button onClick={()=>setActive("Products")}>Add your first product →</button></div>
    <div className="section-title"><h2>Store overview</h2><span className="period">Today　⌄</span></div>
    <div className="stat-grid">{stats.map(({label,value,note,icon:Icon})=><article className="stat-card" key={label}><div className="stat-top"><span>{label}</span><Icon size={18}/></div><strong>{value}</strong><small>{note}</small></article>)}</div>
    <div className="lower-grid"><article className="panel"><div className="panel-heading"><div><h2>Sales activity</h2><p>Daily revenue performance</p></div><span className="pill">Last 7 days</span></div><div className="empty-chart"><div className="chart-icon"><BarChart3 size={25}/></div><b>No sales data yet</b><p>Completed sales will appear here once your POS is connected.</p><div className="chart-lines"><i/><i/><i/><i/></div></div></article>
    <article className="panel"><div className="panel-heading"><div><h2>Quick actions</h2><p>Common store tasks</p></div></div>{[{label:"Start a sale",desc:"Open the checkout terminal",target:"Point of sale",icon:ShoppingCart},{label:"Add a product",desc:"Build your product catalog",target:"Products",icon:Package},{label:"Manage customers",desc:"Customer records and balances",target:"Customers",icon:Users}].map(({label,desc,target,icon:Icon})=><button className="quick-action" key={label} onClick={()=>setActive(target)}><span className="quick-icon"><Icon size={18}/></span><span><b>{label}</b><small>{desc}</small></span><span className="arrow">→</span></button>)}</article></div>
    <footer>Ultimate POS <span>·</span> Multi-store management, made simpler <span className="footer-right">API status: not connected</span></footer>
   </div></section>
 </main>;
}
