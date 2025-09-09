
(function(){
  function reloadList(){
    fetch('admin.php?ajax=1', {cache:'no-store'})
      .then(r=>r.text())
      .then(html=>{
        var el = document.getElementById('adminList');
        if(el){ el.innerHTML = html; }
      })
      .catch(()=>{});
  }
  setInterval(reloadList, 5000);
  document.addEventListener('submit', function(e){
    var form = e.target;
    if(form.classList && form.classList.contains('admin-action')){
      e.preventDefault();
      var fd = new FormData(form);
      fd.append('ajax','1');
      fetch('admin.php', {method:'POST', body: fd})
        .then(r=>r.json())
        .then(j=>{
          if(j && j.ok){
            showToast('Booking #'+j.id+' '+j.status+'.');
            reloadList();
          } else {
            showToast('Action failed.');
          }
        })
        .catch(()=>showToast('Network error.'));
    }
  });
  reloadList();
})();
