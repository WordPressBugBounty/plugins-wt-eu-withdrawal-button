/**
 * Root application component with hash-based routing.
 *
 * @package Wbte_Eu_Withdrawal_Button
 */

import { Router, Route, Switch } from 'wouter';
import { useHashLocation } from 'wouter/use-hash-location';
import AdminLayout from './layouts/AdminLayout';
import ReviewBanner from './components/ReviewBanner';
import RequestsList from './components/RequestsList';
import RequestDetail from './components/RequestDetail';
import SettingsPage from './components/SettingsPage';

const App = () => {
	return (
		<Router hook={ useHashLocation }>
			<AdminLayout>
				<ReviewBanner />
				<Switch>
					<Route path="/" component={ RequestsList } />
					<Route path="/request/:id" component={ RequestDetail } />
					<Route path="/settings" component={ SettingsPage } />
				</Switch>
			</AdminLayout>
		</Router>
	);
};

export default App;
