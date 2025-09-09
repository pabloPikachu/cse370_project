(function(){
  function loadTable(){
    fetch('my_bookings.php?ajax=1', {cache:'no-store'})
      .then(r=>r.text())
      .then(html=>{
        var el = document.getElementById('bookingsTable');
        if(el){ el.innerHTML = html; applyClientFilters(); bindCancel(); }
      });
  }
  function bindCancel(){
    document.querySelectorAll('form.cancel-form').forEach(function(form){
      form.addEventListener('submit', function(e){
        e.preventDefault();
        var fd = new FormData(form); fd.append('ajax','1');
        fetch('my_bookings.php',{method:'POST', body:fd}).then(r=>r.json()).then(j=>{
          if(j&&j.ok){ showToast('Booking cancelled.'); loadTable(); } else { showToast('Cancel failed.'); }
        }).catch(()=>showToast('Network error.'));
      });
    });
  }
  function applyClientFilters(){
    var status=(document.getElementById('statusFilter')||{}).value||'';
    var q=(document.getElementById('searchBox')||{}).value||''; q=q.toLowerCase();
    var rows=document.querySelectorAll('#bookingsTable table tr');
    rows.forEach(function(tr,i){
      if(i===0)return;
      var tds=tr.querySelectorAll('td'); if(tds.length<7)return;
      var resource=(tds[2].textContent||'').toLowerCase();
      var purpose =(tds[3].textContent||'').toLowerCase();
      var st=(tds[6].textContent||'').toLowerCase();
      var show=true;
      if(status && st.indexOf(status)===-1) show=false;
      if(q && (resource.indexOf(q)===-1 && purpose.indexOf(q)===-1)) show=false;
      tr.style.display=show?'':'';
    });
  }
  setInterval(loadTable,10000);
  ['statusFilter','searchBox'].forEach(function(id){
    var el=document.getElementById(id); if(el){ el.addEventListener('input', applyClientFilters); }
  });
  loadTable();
})();