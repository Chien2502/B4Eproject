document.addEventListener("DOMContentLoaded", function() {
  // Hàm để tải nội dung từ một tệp và chèn vào một phần tử
  const loadComponent = (url, elementId) => {
    fetch(url)
      .then(response => response.text())
      .then(data => {
        document.getElementById(elementId).innerHTML = data;
      });
  };

  // Tải header và footer
  if (document.getElementById('main-header')) {
    loadComponent("layout/header.html", "main-header");
  }
  if (document.getElementById('main-footer')) {
    loadComponent("layout/footer.html", "main-footer");
  }

  // Xử lý nút Back to Top
  const backToTopButton = document.getElementById('backToTop');
  if (backToTopButton) {
    window.addEventListener('scroll', () => {
      if (window.scrollY > 300) {
        backToTopButton.style.display = 'block'; 
      } else {
        backToTopButton.style.display = 'none';
      }
    });

    backToTopButton.addEventListener('click', () => {
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    });
  }
});


