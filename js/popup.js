// js/popup.js

function mostrarPopup(mensagem, tipo = 'info') {
    const popup = document.createElement('div');
    popup.className = `popup ${tipo}`;
    popup.innerText = mensagem;
  
    document.body.appendChild(popup);
  
    setTimeout(() => {
      popup.classList.add('fadeout');
    }, 4000); // começa a desaparecer após 4 segundos
  
    setTimeout(() => {
      popup.remove();
    }, 5000); // remove após 5 segundos
  }
  