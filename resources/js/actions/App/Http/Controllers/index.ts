import Settings from './Settings'
import Admin from './Admin'

const Controllers = {
    Settings: Object.assign(Settings, Settings),
    Admin: Object.assign(Admin, Admin),
}

export default Controllers