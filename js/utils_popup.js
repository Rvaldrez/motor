// js/utils_popup.js

//(function () {
  //  if (!window.mostrarPopup) {
  //    window.mostrarPopup = function (msg) {
    //    const popup = document.getElementById('popupMensagem');
      //  const texto = document.getElementById('popupTexto');
  
//if (!popup || !texto) {
          //console.warn("❌ Popup HTML não encontrado.");
          //return;
        //}
  
       // texto.innerText = msg;
       // popup.style.display = 'flex';
      //};
   // }
  
    //if (!window.fecharPopup) {
     // window.fecharPopup = function () {
       // const popup = document.getElementById('popupMensagem');
       // if (popup) popup.style.display = 'none';
      //};
    //}
 // })();
  


 (function () {
  let callbackAoFechar = null;

  if (!window.mostrarPopup) {
    window.mostrarPopup = function (msg, callback = null) {
      const popup = document.getElementById('popupMensagem');
      const texto = document.getElementById('popupTexto');

      if (!popup || !texto) {
        console.warn("❌ Popup HTML não encontrado.");
        return;
      }

      texto.innerText = msg;
      popup.style.display = 'flex';

      callbackAoFechar = typeof callback === 'function' ? callback : null;
    };
  }

  if (!window.fecharPopup) {
    window.fecharPopup = function () {
      const popup = document.getElementById('popupMensagem');
      if (popup) popup.style.display = 'none';

      if (callbackAoFechar) {
        callbackAoFechar(); // ✅ Executa ação após fechar
        callbackAoFechar = null; // reseta
      }
    };
  }
})();
