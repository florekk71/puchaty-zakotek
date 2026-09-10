(() => {
 'use strict';
 const hosts=[$('calendarBoard'),$('calendarMobileDays')];
 let gesture=null,ignoreClickUntil=0;
 const enabled=()=>matchMedia('(max-width:780px)').matches&&$('calendar').classList.contains('active')&&['week','day'].includes($('calendarMode').value)&&!document.querySelector('dialog[open]');
 function cancel(){gesture=null;}
 for(const host of hosts){
  host.addEventListener('touchstart',event=>{
   if(!enabled()||event.touches.length!==1||event.target.closest('input,select,textarea,a')){cancel();return;}
   const touch=event.touches[0];gesture={id:touch.identifier,x:touch.clientX,y:touch.clientY,time:Date.now(),axis:null,mode:$('calendarMode').value,date:$('calendarDate').value};
  },{passive:true});
  host.addEventListener('touchmove',event=>{
   if(!gesture)return;
   if(event.touches.length!==1){cancel();return;}
   const touch=event.touches[0];if(touch.identifier!==gesture.id){cancel();return;}
   const dx=touch.clientX-gesture.x,dy=touch.clientY-gesture.y;
   if(!gesture.axis&&Math.max(Math.abs(dx),Math.abs(dy))>12){
    if(Math.abs(dx)>Math.abs(dy)*1.5)gesture.axis='horizontal';else{cancel();return;}
   }
   if(gesture.axis==='horizontal'){
    if(event.cancelable)event.preventDefault();
    ignoreClickUntil=Date.now()+600;
   }
  },{passive:false});
  host.addEventListener('touchend',event=>{
   const g=gesture;cancel();if(!g)return;
   const touch=[...event.changedTouches].find(t=>t.identifier===g.id);if(!touch)return;
   const dx=touch.clientX-g.x,dy=touch.clientY-g.y;
   if(g.axis!=='horizontal'||Math.abs(dx)<60||Math.abs(dx)<Math.abs(dy)*1.5||Date.now()-g.time>1500)return;
   if(!enabled()||g.mode!==$('calendarMode').value||g.date!==$('calendarDate').value)return;
   if(event.cancelable)event.preventDefault();ignoreClickUntil=Date.now()+600;
   const direction=dx<0?1:-1;
   const step=host.id==='calendarMobileDays'?1:7;
   const selected=($('calendarMode').value==='week'?salonCalendarDay:null)||$('calendarDate').value||today();
   const date=new Date(selected+'T12:00:00');
   date.setDate(date.getDate()+direction*step);
   const iso=calendarIso(date);
   salonCalendarDay=iso;$('calendarDate').value=iso;renderCalendar();
  },{passive:false});
  host.addEventListener('touchcancel',cancel,{passive:true});
  host.addEventListener('click',event=>{
   if(Date.now()<ignoreClickUntil){event.preventDefault();event.stopImmediatePropagation();}
  },true);
 }
})();
