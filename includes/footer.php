<?php
// includes/footer.php
?>
    </div> <!-- / .flex-1 overflow-auto p-6 -->
  </main>
</div><!-- / .flex h-screen -->

<style>
@media (max-width: 768px) {
  #sideNav {
    height: auto;
    max-height: 60px;
    overflow: hidden;
  }
  #sideNav.nav-expanded {
    max-height: 100vh;
    overflow-y: auto;
  }
}
@media (min-width: 768px) {
  .nav-collapsed {
    width: 4rem;
  }
}
.nav-collapsed .nav-text, .nav-collapsed .logo-text {
  display: none;
}
.nav-collapsed #toggleNav i {
  transform: rotate(180deg);
}
.submenu-arrow {
  transition: transform 0.2s;
}
.submenu-open .submenu-arrow {
  transform: rotate(180deg);
}
</style>

<script>
function toggleSubmenu(button) {
  const submenu = button.nextElementSibling;
  button.classList.toggle('submenu-open');
  if(submenu.style.maxHeight) {
    submenu.style.maxHeight = null;
    submenu.style.opacity = '0';
    setTimeout(() => {
      submenu.classList.add('hidden');
    }, 200);
  } else {
    submenu.classList.remove('hidden');
    submenu.style.opacity = '1';
    submenu.style.maxHeight = submenu.scrollHeight + "px";
  }
}

document.getElementById('toggleNav').addEventListener('click', function() {
  const nav = document.getElementById('sideNav');
  const isMobile = window.innerWidth < 768;
  if(isMobile) {
    nav.classList.toggle('nav-expanded');
    const icon = this.querySelector('i');
    if(nav.classList.contains('nav-expanded')) {
      icon.className = 'ri-close-line';
    } else {
      icon.className = 'ri-menu-line';
    }
  } else {
    nav.classList.toggle('nav-collapsed');
    const icon = this.querySelector('i');
    if(nav.classList.contains('nav-collapsed')) {
      icon.className = 'ri-menu-unfold-line';
    } else {
      icon.className = 'ri-menu-fold-line';
    }
  }
});

window.addEventListener('resize', function() {
  const nav = document.getElementById('sideNav');
  if(window.innerWidth >= 768) {
    nav.classList.remove('nav-expanded');
  } else {
    nav.classList.remove('nav-collapsed');
  }
});

// Yeni İşlem Menü
function toggleNewActionMenu(e){
  e.stopPropagation();
  document.getElementById('newActionMenu').classList.toggle('hidden');
}
document.addEventListener('click', function(e) {
  const menu = document.getElementById('newActionMenu');
  if(!menu.contains(e.target)){
    menu.classList.add('hidden');
  }
});

// Bildirim Menü
function toggleNotificationMenu(e){
  e.stopPropagation();
  document.getElementById('notificationMenu').classList.toggle('hidden');
}
document.addEventListener('click', function(e) {
  const menu = document.getElementById('notificationMenu');
  if(!menu.contains(e.target)){
    menu.classList.add('hidden');
  }
});

// Dark Mode
let isDarkMode = false;
function toggleDarkMode(btn){
  isDarkMode = !isDarkMode;
  const icon = btn.querySelector('i');
  if(isDarkMode){
    icon.className = 'ri-sun-line';
    document.body.classList.add('bg-gray-900');
    document.body.classList.remove('bg-gray-50');
  } else {
    icon.className = 'ri-moon-line';
    document.body.classList.remove('bg-gray-900');
    document.body.classList.add('bg-gray-50');
  }
}
</script>

</body>
</html>
