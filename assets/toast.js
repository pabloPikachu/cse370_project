(function(global){
  function showToast(msg, duration){
    duration = duration || 3000;
    var t = document.createElement('div');
    t.textContent = msg;
    t.style.position='fixed';
    t.style.right='16px';
    t.style.bottom='16px';
    t.style.background='#222';
    t.style.color='#fff';
    t.style.padding='10px 12px';
    t.style.borderRadius='6px';
    t.style.boxShadow='0 2px 8px rgba(0,0,0,.2)';
    t.style.zIndex='9999';
    document.body.appendChild(t);
    setTimeout(function(){ t.remove(); }, duration);
  }
  global.showToast = showToast;
})(window);