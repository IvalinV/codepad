<?php

namespace App\Native\Layouts;

use Native\Mobile\Edge\Layouts\Builders\Tab;
use Native\Mobile\Edge\Layouts\Builders\TabBar;
use Native\Mobile\Edge\Layouts\NativeLayout;
use Native\Mobile\Edge\NativeComponent;

/**
 * The bottom bar that carries Codepad's three areas.
 *
 * A layout rather than an inline `<native:bottom-nav>` in each screen: the
 * bar is identical across all three, and one declaration means a fourth area
 * cannot arrive on two screens and go missing on the third.
 *
 * These are areas, not history. Reading, editing and the delete confirmation
 * are *pushed* screens with a back button and no bar — a tab that highlighted
 * itself while the user was three levels deep would be lying about where they
 * are. The framework highlights the tab owning the current URI on its own, so
 * nothing here tracks that.
 */
class TabsLayout extends NativeLayout
{
    public function tabBar(NativeComponent $screen): ?TabBar
    {
        return TabBar::make()
            ->add(Tab::link('Snippets', '/', icon: 'list'))
            ->add(Tab::link('Capture', '/snippets/new', icon: 'content_paste'))
            ->add(Tab::link('Settings', '/settings', icon: 'settings'));
    }
}
