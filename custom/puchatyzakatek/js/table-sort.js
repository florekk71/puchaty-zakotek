(() => {
 'use strict';
 const collator=new Intl.Collator('pl',{numeric:true,sensitivity:'base'}),prepared=new WeakSet();
 const blank=value=>!value||/^(—|–|-)$/.test(value);
 function text(cell){return (cell?.dataset.sortValue??cell?.innerText??cell?.textContent??'').replace(/\u00a0|\u202f/g,' ').trim();}
 function number(value){
  let v=value.replace(/\s/g,'').replace(/(?:zł|PLN|%|kg)$/i,'');
  if(!/^[+-]?\d+(?:[.,]\d+)*$/.test(v))return null;
  if(v.includes(','))v=v.replace(/\./g,'').replace(',','.');
  const n=Number(v);return Number.isFinite(n)?n:null;
 }
 function date(value){
  let m=value.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
  if(m)return m.slice(1).map(v=>v||'00').join('');
  m=value.match(/^(\d{2})\.(\d{2})\.(\d{4})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?/);
  return m?[m[3],m[2],m[1],m[4]||'00',m[5]||'00',m[6]||'00'].join(''):null;
 }
 function sort(table,header,index){
  const body=table.tBodies[0];if(!body)return;
  const rows=[...body.rows].filter(r=>!r.matches('[data-sort-fixed],.total,.summary')&&!/^(razem|suma|łącznie)\s*:?$/i.test(text(r.cells[0]))&&r.cells.length===table.tHead.rows[0].cells.length&&![...r.cells].some(c=>c.colSpan>1||c.rowSpan>1));
  const values=rows.map(r=>text(r.cells[index])),filled=values.filter(v=>!blank(v));
  const label=header.dataset.sortLabel;
  const isDate=filled.length&&filled.every(v=>date(v)!==null);
  const isNumber=!/telefon|nip|ean|kod|numer/i.test(label)&&filled.length&&filled.every(v=>number(v)!==null);
  const desc=header.getAttribute('aria-sort')==='ascending',sign=desc?-1:1;
  const sorted=rows.map((row,i)=>({row,value:values[i],i})).sort((a,b)=>{
   if(blank(a.value)||blank(b.value))return blank(a.value)===blank(b.value)?a.i-b.i:blank(a.value)?1:-1;
   const result=isDate?date(a.value).localeCompare(date(b.value)):isNumber?number(a.value)-number(b.value):collator.compare(a.value,b.value);
   return sign*result||a.i-b.i;
  });
  // Keep summary/colspan rows in their original positions, and move whole rows with actions.
  const order=[...body.rows];let i=0;const movable=new Set(rows);
  body.append(...order.map(r=>movable.has(r)?sorted[i++].row:r));
  for(const h of table.tHead.rows[0].cells){if(h.dataset.sortLabel===undefined)continue;h.setAttribute('aria-sort',h===header?(desc?'descending':'ascending'):'none');const button=h.querySelector('.table-sort');button.textContent=h===header?(desc?'↓':'↑'):'↕';button.setAttribute('aria-label','Sortuj: '+h.dataset.sortLabel+(h===header&&!desc?' — malejąco':' — rosnąco'));}
 }
 function prepare(){
  document.querySelectorAll('.content table,.view table,#pzModal table').forEach(table=>{
   if(prepared.has(table)||!table.tHead||table.tHead.rows.length!==1||!table.tBodies.length||table.querySelector('input,select,textarea')||table.closest('#receipt'))return;
   prepared.add(table);
   [...table.tHead.rows[0].cells].forEach((header,index)=>{
    const label=header.textContent.trim();if(!label||/^(akcje|kartoteka|operacje)$/i.test(label)||header.colSpan>1)return;
    header.dataset.sortLabel=label;header.setAttribute('aria-sort','none');header.classList.add('sortable-column');
    const button=document.createElement('button');button.type='button';button.className='table-sort';button.textContent='↕';button.setAttribute('aria-label','Sortuj: '+label+' — rosnąco');header.append(button);
    header.addEventListener('click',()=>sort(table,header,index));
   });
  });
 }
 let queued=false;
 const observer=new MutationObserver(()=>{if(queued)return;queued=true;queueMicrotask(()=>{queued=false;prepare();});});
 observer.observe(document.body,{childList:true,subtree:true});prepare();
})();
