import Sidebar from './Sidebar';
import TopBar from './TopBar';

export default function AppLayout({ children, activePage, wizardProgress }) {
    return (
        <>
            <Sidebar activePage={activePage} wizardProgress={wizardProgress} />
            <TopBar />
            <main className="ml-64 pt-16 min-h-screen bg-background">
                {children}
            </main>
        </>
    );
}
