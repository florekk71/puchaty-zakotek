'use strict';
function renderVisitDashboard(r){
 const periodValue=period();$('visitReportFrom').value=periodValue.from;$('visitReportTo').value=periodValue.to;
 const visitDate=v=>(v.paymentState.completedDate||v.visit_date).slice(0,10),allDates=[...r.visits.map(visitDate),...r.received.map(v=>v.paymentState.date)].sort();
 const from=periodValue.from||allDates[0]||today(),to=periodValue.to||allDates.at(-1)||today();
 const months=[];for(let year=Number(from.slice(0,4)),m=Number(from.slice(5,7));year*100+m<=Number(to.slice(0,4))*100+Number(to.slice(5,7));){months.push(year+'-'+String(m).padStart(2,'0'));if(++m>12){m=1;year++;}if(months.length>=1200)break;}
 const annual=months.length>24,daily=(Date.parse(to)-Date.parse(from))/86400000<=62;let keys=annual?[...new Set(months.map(m=>m.slice(0,4)))]:months;if(daily){keys=[];for(let t=Date.parse(from);t<=Date.parse(to);t+=86400000)keys.push(new Date(t).toISOString().slice(0,10));}const labels=keys.map(k=>daily?k.slice(8)+'.'+k.slice(5,7):annual?k:k.slice(5)+'.'+k.slice(2,4));
 const amounts=new Map(),counts=new Map(),days=Array(7).fill(0);
 r.visits.forEach(v=>{const d=visitDate(v),k=d.slice(0,daily?10:annual?4:7);amounts.set(k,(amounts.get(k)||0)+Math.round(Number(v.amount_total)*100));counts.set(k,(counts.get(k)||0)+1);days[(new Date(d+'T12:00:00Z').getUTCDay()+6)%7]++;});
 const total=[...amounts.values()].reduce((a,b)=>a+b,0),received=r.received.reduce((n,v)=>n+Math.round(Number(v.amount_total)*100),0),average=r.visits.length?Math.round(total/r.visits.length):0;
 const tile=(label,value,sub)=>'<div class="pz-kpi"><span>'+esc(label)+'</span><strong>'+esc(value)+'</strong><span>'+esc(sub)+'</span></div>';
 $('visitReportCharts').innerHTML='<style>'+pzReportStyle+'</style><div class="pz-dashboard"><p class="pz-subtitle">Okres: '+esc(periodValue.from||'od początku ewidencji')+' — '+esc(periodValue.to||'bez daty końcowej')+'. Wykresy wizyt dotyczą wizyt rozliczonych, według daty wykonania.</p><div class="pz-kpis">'+tile('Rozliczone wizyty',r.visits.length,'Liczba wykonanych i rozliczonych wizyt')+tile('Wartość wizyt',money(total),'Według daty wykonania')+tile('Średnia wartość wizyty',money(average),'Wartość podzielona przez liczbę wizyt')+'</div><div class="pz-charts">'+pzChartCard('Wartość wizyt w czasie',daily?'Podsumowanie dzienne':annual?'Podsumowanie roczne':'Podsumowanie miesięczne',pzTimeline(labels,[{name:'Wartość wizyt',values:keys.map(k=>amounts.get(k)||0)}]))+pzChartCard('Ile wizyt obsługujesz?',daily?'Liczba rozliczonych wizyt dziennie':annual?'Liczba rozliczonych wizyt w roku':'Liczba rozliczonych wizyt w miesiącu',pzTimeline(labels,[{name:'Rozliczone wizyty',values:keys.map(k=>counts.get(k)||0)}],true,'count'))+pzChartCard('Najbardziej pracowite dni','Liczba rozliczonych wizyt według dnia wykonania',pzTimeline(['Pon.','Wt.','Śr.','Czw.','Pt.','Sob.','Niedz.'],[{name:'Wizyty',values:days}],true,'count'))+pzChartCard('Otrzymane wpłaty za wizyty','Razem '+money(received)+'. Według daty otrzymania wpłaty — również za wizyty wykonane wcześniej.',pzDonut(pzGroup(r.received,v=>v.payment_type||'Nie określono',v=>Math.round(Number(v.amount_total)*100)),'Otrzymano'))+'</div></div>';
}
document.addEventListener('change',e=>{
 if(!['visitReportFrom','visitReportTo'].includes(e.target.id)||!data)return;
 const from=$('visitReportFrom').value,to=$('visitReportTo').value;
 if(from&&to&&from>to){message('Data „Od” nie może być późniejsza niż „Do”. Raport nadal pokazuje poprzedni zakres.',true);return;}
 $('reportFrom').value=from;$('reportTo').value=to;renderReports();
});
document.addEventListener('click',e=>{
 const preset=e.target.closest('[data-visit-period]');
 if(preset&&data){const now=today();$('reportFrom').value=preset.dataset.visitPeriod==='month'?now.slice(0,7)+'-01':now.slice(0,4)+'-01';$('reportTo').value=now;renderReports();}
 if(e.target.closest('#printVisitReport')){
 const frame=document.createElement('iframe');frame.style.cssText='position:fixed;left:-10000px;width:1000px;height:1000px';document.body.appendChild(frame);const doc=frame.contentDocument;
 doc.open();doc.write('<!doctype html><html lang="pl"><meta charset="utf-8"><title>Raport wizyt — Puchaty Zakątek</title><style>'+pzReportStyle+'body{font:12px Arial;color:#492d38}table{border-collapse:collapse;width:100%}th,td{border:1px solid #eedde2;padding:7px}button,input,label{display:none}@page{size:A4;margin:12mm}</style><body><h2>Puchaty Zakątek — raport wizyt</h2>'+$('visitReportCharts').innerHTML+'<h3>Podsumowanie miesięczne</h3>'+$('reportTable').innerHTML+'</body></html>');doc.close();frame.contentWindow.focus();frame.contentWindow.print();setTimeout(()=>frame.remove(),60000);
 }
});
